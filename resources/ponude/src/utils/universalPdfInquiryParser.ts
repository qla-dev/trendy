import * as pdfjsLib from "pdfjs-dist"
import pdfWorkerUrl from "pdfjs-dist/build/pdf.worker.min.mjs?url"

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerUrl

export type UniversalPdfInquiryRow = {
  positionNumber: string
  code: string
  name: string
  quantity: number
  staffelQuantityText?: string
}

export type UniversalPdfInquiryResult = {
  rows: UniversalPdfInquiryRow[]
  /** Full extracted document text, used for customer recognition. */
  text: string
  /** Layout-derived rows used only as a conservative fallback by the caller. */
  grid: string[][]
  hasTextLayer: boolean
  pageCount: number
}

type PdfTextItem = {
  text: string
  x: number
  y: number
  width: number
}

type PdfTextLine = {
  y: number
  items: PdfTextItem[]
}

type PdfTextPage = {
  lines: PdfTextLine[]
}

type PdfColumnKey =
  | "position"
  | "article"
  | "name"
  | "quantity"
  | "priceScale"
  | "unit"
  | "price"
  | "delivery"

type PdfColumnAnchor = {
  key: PdfColumnKey
  x: number
}

type PdfTableLayout = {
  anchors: PdfColumnAnchor[]
  hasPositionColumn: boolean
  hasSeparateNameColumn: boolean
}

const POSITION_HEADER_ALIASES = [
  "pos", "position", "posnr", "positionsnr", "positionsnummer",
  "line", "lineno", "linenumber", "lineitem",
]

const QUANTITY_HEADER_ALIASES = [
  "anfragemenge", "requiredqty", "requiredquantity", "quantityrequired",
  "bestellmenge", "orderqty", "orderquantity", "requestqty", "requestquantity",
  "menge", "qty", "quantity", "kolicina", "cantidad", "quantite", "qte",
  "anzahl", "bedarf", "stuckzahl", "stueckzahl", "pcs", "pieces",
]

const CODE_HEADER_ALIASES = [
  "materialnummer", "materialnr", "materialno", "materialnumber", "matnr", "material",
  "sapartikelnummer", "sapmaterial", "sapnummer", "sapno", "sapnumber",
  "artikelnummer", "artikelnr", "artikelno", "artnr", "artikelcode",
  "articlecode", "articleid", "articleno", "articlenumber",
  "partno", "partnumber", "partid", "teilnummer", "zeichnungsnummer", "drawingno", "drawingnumber",
  "productno", "productnumber", "stockno", "sku", "itemcode",
  "itemno", "itemnumber", "itemid", "code", "artikel", "article", "part", "item",
]

const NAME_HEADER_ALIASES = [
  "materialbeschreibung", "materialbezeichnung",
  "artikelbeschreibung", "artikelbezeichnung",
  "beschreibung", "bezeichnung", "description", "designation",
  "benennung", "kurztext", "kurzbeschreibung", "artikeltext",
  "produktbezeichnung", "itemdescription", "productdescription",
  "descripcion", "designacion", "name", "text",
]

const PRICE_SCALE_HEADER_ALIASES = [
  "pricescale", "pricescaledodatnekolicine", "dodatnekolicine",
  "staffel", "staffelpreise", "staffelmengen", "preisstaffel",
  "quantityscale", "quantitybreaks", "mengebis", "abmenge",
]

const UNIT_HEADER_ALIASES = ["einheit", "unit", "uom", "mengeneinheit"]
const PRICE_HEADER_ALIASES = ["einzelpreis", "preisproeinheit", "priceperunit", "unitprice", "price", "preis"]
const DELIVERY_HEADER_ALIASES = ["lieferdatum", "deliverydate", "liefertermin", "delivery"]

function normalizeHeader(value: unknown): string {
  return String(value ?? "")
    .trim()
    .toLocaleLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "")
}

function joinPdfItems(items: PdfTextItem[]): string {
  return [...items]
    .sort((a, b) => a.x - b.x)
    .map((item) => item.text.trim())
    .filter(Boolean)
    .join(" ")
    .replace(/\s+/g, " ")
    .trim()
}

/**
 * PDF text extraction often returns separate text objects for each header.
 * The approximate in-object offset also handles PDFs where the whole header
 * row is returned as a single text object (e.g. "Pos. Material Anfragemenge").
 */
type PdfHeaderLabelMatch = {
  x: number
  itemIndex: number
  normalizedItem: string
}

function findPdfLabelMatch(line: PdfTextLine, aliases: string[]): PdfHeaderLabelMatch | null {
  const ordered = [...line.items].sort((a, b) => a.x - b.x)
  for (let itemIndex = 0; itemIndex < ordered.length; itemIndex += 1) {
    const item = ordered[itemIndex]
    const normalized = normalizeHeader(item.text)
    for (const alias of aliases) {
      const matchIndex = normalized.indexOf(alias)
      if (matchIndex < 0) continue
      const ratio = normalized.length > 0 ? matchIndex / normalized.length : 0
      return {
        x: item.x + Math.max(0, item.width) * ratio,
        itemIndex,
        normalizedItem: normalized,
      }
    }
  }
  return null
}

function getPdfLabelX(line: PdfTextLine, aliases: string[]): number | null {
  return findPdfLabelMatch(line, aliases)?.x ?? null
}

function findPdfTableLayout(lines: PdfTextLine[]): { headerIndex: number; layout: PdfTableLayout } | null {
  for (let index = 0; index < lines.length; index += 1) {
    const header = lines[index]
    const positionX = getPdfLabelX(header, POSITION_HEADER_ALIASES)
    const quantityX = getPdfLabelX(header, QUANTITY_HEADER_ALIASES)
    const codeMatch = findPdfLabelMatch(header, CODE_HEADER_ALIASES)
    const nameMatch = findPdfLabelMatch(header, NAME_HEADER_ALIASES)
    const codeX = codeMatch?.x ?? null
    const nameX = nameMatch?.x ?? null
    const articleX = codeX ?? nameX
    if (quantityX === null || articleX === null) continue

    // A combined heading such as "Material/Beschreibung" is one cell.
    // Separate "Material" and "Beschreibung" header cells remain separate,
    // even when their wording is similar.
    const combinedArticle = Boolean(
      codeMatch &&
      nameMatch &&
      codeMatch.itemIndex === nameMatch.itemIndex &&
      /(?:material|artikel|article|item|part|product)(?:beschreibung|bezeichnung|description|text)/.test(codeMatch.normalizedItem),
    )
    const hasSeparateNameColumn = Boolean(
      codeX !== null &&
      nameX !== null &&
      !combinedArticle &&
      Math.abs(nameX - codeX) >= 18 &&
      ((codeX < quantityX && nameX < quantityX) || (codeX > quantityX && nameX > quantityX)),
    )

    const anchors: PdfColumnAnchor[] = []
    if (positionX !== null) anchors.push({ key: "position", x: positionX })
    anchors.push({ key: "article", x: articleX })
    if (hasSeparateNameColumn && nameX !== null) anchors.push({ key: "name", x: nameX })
    anchors.push({ key: "quantity", x: quantityX })

    const optional: Array<[PdfColumnKey, number | null]> = [
      ["priceScale", getPdfLabelX(header, PRICE_SCALE_HEADER_ALIASES)],
      ["unit", getPdfLabelX(header, UNIT_HEADER_ALIASES)],
      ["price", getPdfLabelX(header, PRICE_HEADER_ALIASES)],
      ["delivery", getPdfLabelX(header, DELIVERY_HEADER_ALIASES)],
    ]
    for (const [key, x] of optional) {
      if (x !== null && !anchors.some((anchor) => anchor.key === key)) {
        anchors.push({ key, x })
      }
    }

    anchors.sort((a, b) => a.x - b.x)
    // A layout must provide a meaningful, distinct article and quantity column.
    const articleAnchor = anchors.find((anchor) => anchor.key === "article")
    const quantityAnchor = anchors.find((anchor) => anchor.key === "quantity")
    if (!articleAnchor || !quantityAnchor || Math.abs(articleAnchor.x - quantityAnchor.x) < 12) continue

    return {
      headerIndex: index,
      layout: {
        anchors,
        hasPositionColumn: positionX !== null,
        hasSeparateNameColumn,
      },
    }
  }

  return null
}

function getColumnText(line: PdfTextLine, anchors: PdfColumnAnchor[], key: PdfColumnKey): string {
  const index = anchors.findIndex((anchor) => anchor.key === key)
  if (index < 0) return ""

  const current = anchors[index]
  const previous = anchors[index - 1]
  const next = anchors[index + 1]
  const left = previous ? (previous.x + current.x) / 2 : Number.NEGATIVE_INFINITY
  const right = next ? (current.x + next.x) / 2 : Number.POSITIVE_INFINITY

  return joinPdfItems(line.items.filter((item) => item.x >= left && item.x < right))
}

function parseFirstQuantity(value: string): number | null {
  const text = String(value ?? "").trim().replace(/\s+/g, " ")
  if (!text) return null

  // The quantity cell may contain a unit ("4 ST") or extra supplier fields.
  // Read the first numeric token only, instead of concatenating later dates.
  const token = text.match(/-?\d[\d.,]*/)?.[0]
  if (!token) return null

  let normalized = token
  if (/^\d{1,3}(\.\d{3})+$/.test(normalized)) {
    normalized = normalized.replace(/\./g, "")
  } else if (normalized.includes(",") && normalized.includes(".")) {
    normalized = normalized.lastIndexOf(",") > normalized.lastIndexOf(".")
      ? normalized.replace(/\./g, "").replace(",", ".")
      : normalized.replace(/,/g, "")
  } else if (normalized.includes(",")) {
    normalized = normalized.replace(",", ".")
  }

  const quantity = Number(normalized)
  return Number.isFinite(quantity) && quantity > 0 ? quantity : null
}

function splitArticleCell(value: string): { code: string; name: string } | null {
  const text = value.replace(/\s+/g, " ").trim()
  if (!text) return null

  // A part/article number must contain at least one digit. This prevents
  // supplier addresses and headings from becoming item codes.
  const match = text.match(/^([A-Za-z0-9][A-Za-z0-9._/-]*)(?:\s+(.*))?$/)
  if (!match || !/\d/.test(match[1])) return null

  const code = match[1].replace(/^[.,:;]+|[.,:;]+$/g, "")
  let name = (match[2] ?? "").trim()

  while (
    name.length >= code.length &&
    name.slice(0, code.length).toLocaleUpperCase() === code.toLocaleUpperCase() &&
    (name.length === code.length || /\s/.test(name[code.length]))
  ) {
    name = name.slice(code.length).trimStart()
  }

  return { code, name: name.replace(/\s+/g, " ").trim() }
}

function normalizePositionNumber(value: string): string {
  const text = value.trim().replace(/\s+/g, "")
  return /^\d{1,8}(?:[./-]\d{1,5})?[.)]?$/.test(text)
    ? text.replace(/[.)]$/, "")
    : ""
}

function isDocumentHeaderOrFooter(text: string): boolean {
  const normalized = normalizeHeader(text)
  const markers = [
    "anfragenummer",
    "druckzeitpunkt",
    "lieferantennummer",
    "angebotsfrist",
    "liefertermin",
    "telefon",
    "emailadresse",
    "seiten",
    "seite",
    "iban",
    "bic",
    "uidnr",
    "www",
    "http",
    "copyright",
  ]
  return markers.some((marker) => normalized.includes(marker))
}

function buildPdfLines(items: PdfTextItem[]): PdfTextLine[] {
  const lines: PdfTextLine[] = []
  for (const item of [...items].sort((a, b) => b.y - a.y || a.x - b.x)) {
    let line = lines.find((candidate) => Math.abs(candidate.y - item.y) <= 3)
    if (!line) {
      line = { y: item.y, items: [] }
      lines.push(line)
    }
    line.items.push(item)
  }
  return lines.sort((a, b) => b.y - a.y)
}

function buildGridFromLines(lines: PdfTextLine[]): string[][] {
  const grid: string[][] = []
  for (const line of lines) {
    const ordered = [...line.items].sort((a, b) => a.x - b.x)
    const cells: string[] = []
    let lastRight = Number.NEGATIVE_INFINITY

    for (const item of ordered) {
      const gap = item.x - lastRight
      if (cells.length === 0 || gap > 20) {
        cells.push(item.text)
      } else {
        cells[cells.length - 1] = `${cells[cells.length - 1]} ${item.text}`.trim()
      }
      lastRight = Math.max(lastRight, item.x + item.width)
    }
    if (cells.length) grid.push(cells)
  }
  return grid
}

function parsePositionRowsFromPages(pages: PdfTextPage[]): UniversalPdfInquiryRow[] {
  const result: UniversalPdfInquiryRow[] = []
  let activeLayout: PdfTableLayout | null = null
  let current: UniversalPdfInquiryRow | null = null

  for (const page of pages) {
    const detected = findPdfTableLayout(page.lines)
    let linesToRead = page.lines

    if (detected) {
      activeLayout = detected.layout
      // Header is not item data. Continue after it, while keeping the current
      // item so a description can continue on the next page.
      linesToRead = page.lines.slice(detected.headerIndex + 1)
    } else if (!activeLayout) {
      // Without an explicit table header we do not guess from dates, addresses,
      // supplier IDs, telephone numbers or delivery terms.
      continue
    }

    for (const line of linesToRead) {
      const lineText = joinPdfItems(line.items)
      if (!lineText || isDocumentHeaderOrFooter(lineText)) continue

      const positionText = getColumnText(line, activeLayout.anchors, "position")
      const articleText = getColumnText(line, activeLayout.anchors, "article")
      const separateNameText = activeLayout.hasSeparateNameColumn
        ? getColumnText(line, activeLayout.anchors, "name")
        : ""
      const quantityText = getColumnText(line, activeLayout.anchors, "quantity")
      const priceScaleText = getColumnText(line, activeLayout.anchors, "priceScale")

      const positionNumber: string = activeLayout.hasPositionColumn
        ? normalizePositionNumber(positionText)
        : String(result.length + (current ? 2 : 1)).padStart(3, "0")
      const quantity = parseFirstQuantity(quantityText)
      const article = splitArticleCell(articleText)

      const isNewItem = Boolean(
        article &&
        quantity !== null &&
        (!activeLayout.hasPositionColumn || positionNumber),
      )

      if (isNewItem && article && quantity !== null) {
        if (current) result.push(current)
        current = {
          positionNumber: positionNumber || String(result.length + 1).padStart(3, "0"),
          code: article.code,
          name: [article.name, separateNameText].filter(Boolean).join(" ").replace(/\s+/g, " ").trim(),
          quantity,
          ...(priceScaleText.trim() ? { staffelQuantityText: priceScaleText.trim() } : {}),
        }
        continue
      }

      if (!current) continue

      // A non-numeric value in the position column or any content in the
      // quantity column means this is not a continuation of the item text.
      // This also filters repeated page headers and footer/company lines.
      if (positionText.trim() || quantityText.trim()) continue

      const continuation = activeLayout.hasSeparateNameColumn
        ? (separateNameText || articleText)
        : articleText
      const cleanContinuation = continuation.replace(/\s+/g, " ").trim()
      if (!cleanContinuation) continue

      current.name = [current.name, cleanContinuation]
        .filter(Boolean)
        .join(" ")
        .replace(/\s+/g, " ")
        .trim()
    }
  }

  if (current) result.push(current)

  // Keep only structurally valid rows and avoid importing accidental headers.
  return result.filter((row) =>
    Boolean(row.code.trim()) &&
    Number.isFinite(row.quantity) &&
    row.quantity > 0 &&
    Boolean(row.positionNumber.trim()),
  )
}

export async function extractUniversalPdfInquiry(file: File): Promise<UniversalPdfInquiryResult> {
  const bytes = new Uint8Array(await file.arrayBuffer())
  const loadingTask = pdfjsLib.getDocument({ data: bytes })
  const pdf = await loadingTask.promise
  const pages: PdfTextPage[] = []
  const documentLines: string[] = []
  let totalTextItems = 0

  try {
    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
      const page = await pdf.getPage(pageNumber)
      const textContent = await page.getTextContent()
      const items: PdfTextItem[] = (textContent.items as Array<Record<string, unknown>>)
        .filter((item) =>
          typeof item.str === "string" &&
          String(item.str).trim().length > 0 &&
          Array.isArray(item.transform),
        )
        .map((item) => ({
          text: String(item.str).trim(),
          x: Number((item.transform as number[])[4]) || 0,
          y: Number((item.transform as number[])[5]) || 0,
          width: Number(item.width) || 0,
        }))

      totalTextItems += items.length
      const lines = buildPdfLines(items)
      pages.push({ lines })
      documentLines.push(...lines.map((line) => joinPdfItems(line.items)))
    }
  } finally {
    await pdf.destroy()
  }

  const grid = pages.flatMap((page) => buildGridFromLines(page.lines))
  return {
    rows: parsePositionRowsFromPages(pages),
    text: documentLines.join("\n"),
    grid,
    hasTextLayer: totalTextItems > 0,
    pageCount: pages.length,
  }
}
