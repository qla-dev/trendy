import {
  useEffect,
  useMemo,
  useState,
  type FormEvent,
} from "react"

import type { CSSProperties } from "react"
import { useAuth } from "../auth/AuthContext"
import { hasPermission } from "../auth/permissions"
import { getQuoteExportCostBreakdown } from "../utils/quoteExportCosts"
import { parseLocalizedNumber } from "../utils/numericInput"
import { getCustomers, type Customer } from "../services/customers"
import * as XLSX from "xlsx"
import "./QuotesDashboard.css"
import "./QuoteTimeAnalysis.css"

import {
  deleteQuote as deleteQuoteFromDatabase,
  getAllQuotes,
  updateQuote,
  type Quote,
} from "../services/quotes"
import {
  addQuoteContactHistory,
  type QuoteContactHistoryEntry,
} from "../services/quoteContactHistory"

/*
 * ==========================================
 * REACT DATEPICKER
 * ==========================================
 *
 * Koristimo vlastiti kalendar umjesto
 * browser <input type="date"> kalendara.
 *
 * Na taj način izgled i početak sedmice
 * ne zavise od Windowsa ili Chromea.
 */
import DatePicker from "react-datepicker"

import {
  bs,
} from "date-fns/locale"

import "react-datepicker/dist/react-datepicker.css"

/*
 * ==========================================
 * POMOĆNI TIP PONUDE SA KUPCEM
 * ==========================================
 *
 * Kupac se u Firebase sprema kao:
 *
 * kupacId
 * kupacNaziv
 *
 * Starije ponude možda nemaju ove podatke,
 * zato su polja opcionalna.
 */
type FollowUpStatus = "waiting" | "contact" | "completed"
type FollowUpQuoteOutcome = "sent" | "accepted" | "rejected"
type ContactChannel = "email" | "telefon" | "sastanak" | "drugo"
type ContactOutcome = "no_response" | "requested_change" | "requested_info" | "negotiation" | "positive" | "negative" | "other"

type QuoteWithCustomer = Quote & {
  kupacId?: string
  kupacNaziv?: string
  datumSlanja?: any
  datumSljedecegKontakta?: any
  statusPracenja?: FollowUpStatus
  napomenaPracenja?: string
  /** True only when a user explicitly wants this sent offer on the follow-up list. */
  pratiPonudu?: boolean
  historijaKontakata?: QuoteContactHistoryEntry[]
}


type DashboardPosition = {
  code?: string
  name?: string
  quantity?: number
  totalCost?: number
  totalCostPerPiece?: number
  totalSellingPrice?: number
  sellingPricePerPiece?: number
}

type DashboardLine = {
  key: string
  code: string
  name: string
  quoteNumber: string
  customer: string
  quantity: number
  costPerPiece: number
  sellingPerPiece: number
  profitPerPiece: number
  marginPercent: number
}

type CustomerAnalysisRow = {
  key: string
  customerName: string
  quoteCount: number
  acceptedCount: number
  rejectedCount: number
  offeredValue: number
  acceptedValue: number
  successRate: number | null
  isUnassigned: boolean
}

function numericValue(value: unknown): number {
  const result = Number(value)
  return Number.isFinite(result) ? result : 0
}

function getPositionFinance(position: DashboardPosition) {
  const quantity = Math.max(0, numericValue(position.quantity))
  const rawCostPerPiece = Math.max(0, numericValue(position.totalCostPerPiece))
  const rawSellingPerPiece = Math.max(0, numericValue(position.sellingPricePerPiece))
  const storedTotalCost = Math.max(0, numericValue(position.totalCost))
  const storedTotalSelling = Math.max(0, numericValue(position.totalSellingPrice))

  const totalCost = storedTotalCost > 0
    ? storedTotalCost
    : rawCostPerPiece * quantity
  const totalSelling = storedTotalSelling > 0
    ? storedTotalSelling
    : rawSellingPerPiece * quantity

  return {
    quantity,
    totalCost,
    totalSelling,
    costPerPiece: quantity > 0
      ? (storedTotalCost > 0 ? totalCost / quantity : rawCostPerPiece)
      : 0,
    sellingPerPiece: quantity > 0
      ? (storedTotalSelling > 0 ? totalSelling / quantity : rawSellingPerPiece)
      : 0,
  }
}

function getQuoteFinance(quote: QuoteWithCustomer) {
  const positions = Array.isArray(quote.positions)
    ? quote.positions as unknown as DashboardPosition[]
    : []
  const summedCost = positions.reduce(
    (sum, position) => sum + getPositionFinance(position).totalCost,
    0,
  )
  const summedSales = positions.reduce(
    (sum, position) => sum + getPositionFinance(position).totalSelling,
    0,
  )
  const storedCost = Math.max(0, numericValue(quote.totalCost))
  const storedSales = Math.max(0, numericValue(quote.totalSellingPrice))

  return {
    cost: storedCost > 0 ? storedCost : summedCost,
    sales: storedSales > 0 ? storedSales : summedSales,
  }
}

function getCustomerGroupingKey(quote: QuoteWithCustomer): string {
  const customerName = String(quote.kupacNaziv || "").trim()
  const customerId = String(quote.kupacId || "").trim()

  if (customerId) return `id:${customerId}`
  if (customerName) return `name:${customerName.toLocaleLowerCase("bs-BA")}`
  return "unassigned-customer"
}

function formatEuro(value: number): string {
  return `${numericValue(value).toLocaleString("de-DE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} €`
}

function formatPercent(value: number): string {
  return `${numericValue(value).toLocaleString("de-DE", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  })}%`
}

// Status svih ponuda u kontrolnoj tabli; "all" je samo filter i nije stvarni status ponude.
function getDashboardStatus(
  quote: QuoteWithCustomer,
): Exclude<StatusFilter, "all"> {
  const status = quote.status as Exclude<StatusFilter, "all"> | undefined
  return status === "sent" || status === "accepted" || status === "rejected"
    ? status
    : "draft"
}

/*
 * ==========================================
 * TIP FILTERA STATUSA
 * ==========================================
 */
type StatusFilter =
  | "all"
  | "draft"
  | "sent"
  | "accepted"
  | "rejected"

/*
 * ==========================================
 * TIP SORTIRANJA
 * ==========================================
 *
 * Određuje redoslijed prikaza ponuda.
 */
type SortOption =
  | "newest"
  | "oldest"
  | "quoteAsc"
  | "quoteDesc"
  | "customerAsc"
  | "customerDesc"
  | "valueDesc"
  | "valueAsc"


  const tdStyle:
  CSSProperties = {
    padding:
      "13px 12px",
    fontSize:
      "14px",
    color:
      "#334155",
    whiteSpace:
      "nowrap",
  }


  const buttonStyle: CSSProperties = {
  padding: "8px 12px",
  border: "1px solid #2563eb",
  borderRadius: "6px",
  background: "#eff6ff",
  color: "#2563eb",
  cursor: "pointer",
  fontSize: "14px",
  fontWeight: 600,
}

function paginationButtonStyle(disabled: boolean, active = false): CSSProperties {
  return {
    minWidth: "34px",
    padding: "7px 10px",
    border: active ? "1px solid #1d4ed8" : "1px solid #cbd5e1",
    borderRadius: "7px",
    background: active ? "#eff6ff" : "#fff",
    color: active ? "#1d4ed8" : disabled ? "#94a3b8" : "#334155",
    fontSize: "13px",
    fontWeight: active ? 700 : 500,
    cursor: disabled ? "not-allowed" : "pointer",
    opacity: disabled ? 0.55 : 1,
  }
}


/*
 * ==========================================
 * IZVOZ POZICIJA U EXCEL (.XLSX)
 * ==========================================
 * Rüstkosten se izvoze kao ukupan trošak programiranja + pripreme za seriju.
 * Stückkosten prikazuje ostale troškove po komadu uvećane za maržu.
 */
function exportQuotePositionsToExcel(quote: QuoteWithCustomer) {
  const headers = [
    "Br.",
    "Šifra",
    "Naziv",
    "Required Qty",
    "Price Scale Qty",
    "Rüstkosten gesamt (€)",
    "Stückkosten / Stück (€)",
    "Cijena / kom (€)",
    "Ukupno za količinu (€)",
  ]

  const hasStaffelPrices = Boolean(
    (quote as any).staffelpreiseAktivno ||
    (quote.positions || []).some((position: any) => Array.isArray(position.staffelpreise) && position.staffelpreise.length > 0),
  )

  // Staffelpreise izvozi kao zaseban red po kombinaciji pozicije i količine.
  // Legacy ponude bez Staffelpreise zadržavaju svoj postojeći format.
  const rows = (quote.positions || []).flatMap((position: any, index: number) => {
    const positionNumber = position.positionNumber || String(index + 1).padStart(3, "0")
    const tiers = Array.isArray(position.staffelpreise) ? position.staffelpreise : []

    if (hasStaffelPrices && tiers.length > 0) {
      return tiers.map((tier: any) => {
        const costs = getQuoteExportCostBreakdown(position, tier.quantity, tier.totalCostPerPiece, tier.marginValuePerPiece)
        return [
          positionNumber,
          position.code || "",
          position.name || "",
          Number(position.quantity) || 0,
          Number(tier.quantity) || 0,
          costs.rustCostTotal,
          costs.stuckCostPerPiece,
          Number(tier.sellingPricePerPiece) || 0,
          Number(tier.totalSellingPrice) || 0,
        ]
      })
    }

    const costs = getQuoteExportCostBreakdown(position, position.quantity)
    return [[
      positionNumber,
      position.code || "",
      position.name || "",
      Number(position.quantity) || 0,
      "",
      costs.rustCostTotal,
      costs.stuckCostPerPiece,
      Number(position.sellingPricePerPiece) || 0,
      Number(position.totalSellingPrice) || 0,
    ]]
  })

  const worksheet = XLSX.utils.aoa_to_sheet([headers, ...rows])
  const euroColumns = [5, 6, 7, 8]
  for (let rowIndex = 1; rowIndex <= rows.length; rowIndex += 1) {
    for (const columnIndex of euroColumns) {
      const cellAddress = XLSX.utils.encode_cell({ r: rowIndex, c: columnIndex })
      const cell = worksheet[cellAddress]
      if (cell && cell.t === "n") cell.z = '#,##0.00 "€"'
    }
  }
  worksheet["!cols"] = [
    { wch: 8 },
    { wch: 18 },
    { wch: 28 },
    { wch: 12 },
    { wch: 15 },
    { wch: 21 },
    { wch: 21 },
    { wch: 17 },
    { wch: 22 },
  ]
  worksheet["!autofilter"] = {
    ref: XLSX.utils.encode_range({
      s: { r: 0, c: 0 },
      e: { r: Math.max(rows.length, 1), c: headers.length - 1 },
    }),
  }
  worksheet["!freeze"] = { xSplit: 0, ySplit: 1 }

  const workbook = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(workbook, worksheet, hasStaffelPrices ? "Staffelpreise" : "Pozicije")

  const safeQuoteNumber = String(quote.brojPonude || quote.id || "ponuda")
    .replace(/[^a-zA-Z0-9_-]+/g, "_")

  XLSX.writeFile(workbook, `Pozicije_${safeQuoteNumber}.xlsx`)
}

function escapeHtml(value: unknown): string {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;")
}

/*
 * ==========================================
 * PRIPREMA PDF PONUDE ZA ŠTAMPU
 * ==========================================
 * Otvara pregled za štampu; korisnik zatim
 * bira "Save as PDF / Sačuvaj kao PDF".
 * Interni troškovi i marža se ne prikazuju kupcu.
 */
function exportQuoteToPdf(quote: QuoteWithCustomer) {
  const printWindow = window.open("", "_blank", "width=1050,height=800")

  if (!printWindow) {
    window.alert(
      "Pregled za PDF je blokiran. Dozvoli otvaranje iskačućih prozora za ovu aplikaciju i pokušaj ponovo.",
    )
    return
  }

  const hasStaffelPrices = Boolean(
    (quote as any).staffelpreiseAktivno ||
    (quote.positions || []).some((position: any) => Array.isArray(position.staffelpreise) && position.staffelpreise.length > 0),
  )

  const rows = (quote.positions || []).flatMap((position: any, index: number) => {
    const positionNumber = position.positionNumber || String(index + 1).padStart(3, "0")
    const tiers = Array.isArray(position.staffelpreise) ? position.staffelpreise : []

    if (hasStaffelPrices && tiers.length > 0) {
      return tiers.map((tier: any) => `
        <tr>
          <td>${escapeHtml(positionNumber)}</td>
          <td>${escapeHtml(position.code || "-")}</td>
          <td>${escapeHtml(position.name || "-")}</td>
          <td class="number">${escapeHtml(position.quantity ?? 0)}</td>
          <td class="number">${escapeHtml(tier.quantity ?? 0)}</td>
          <td class="money">${escapeHtml(formatMoney(Number(tier.sellingPricePerPiece) || 0))}</td>
          <td class="money">${escapeHtml(formatMoney(Number(tier.totalSellingPrice) || 0))}</td>
        </tr>
      `)
    }

    return [`
      <tr>
        <td>${escapeHtml(positionNumber)}</td>
        <td>${escapeHtml(position.code || "-")}</td>
        <td>${escapeHtml(position.name || "-")}</td>
        <td class="number">${escapeHtml(position.quantity ?? 0)}</td>
        <td class="number">-</td>
        <td class="money">${escapeHtml(formatMoney(Number(position.sellingPricePerPiece) || 0))}</td>
        <td class="money">${escapeHtml(formatMoney(Number(position.totalSellingPrice) || 0))}</td>
      </tr>
    `]
  }).join("")

  const html = `<!doctype html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <title>Ponuda ${escapeHtml(quote.brojPonude || "")}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #172033; margin: 0; font-size: 10pt; }
    .topline { border-bottom: 3px solid #1d4ed8; padding-bottom: 12px; margin-bottom: 20px; }
    h1 { font-size: 24pt; margin: 0 0 8px; letter-spacing: .5px; }
    .quote-number { color: #1d4ed8; font-weight: 700; font-size: 12pt; }
    .meta { display: flex; justify-content: space-between; gap: 24px; margin: 18px 0 22px; }
    .meta-block { min-width: 220px; }
    .label { color: #64748b; font-size: 8.5pt; text-transform: uppercase; margin-bottom: 5px; }
    .value { font-size: 11pt; font-weight: 600; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { text-align: left; background: #eef2ff; color: #1e293b; font-size: 8pt; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 6px; vertical-align: top; }
    td { font-size: 8.5pt; }
    .number, .money { text-align: right; white-space: nowrap; }
    .totals { margin: 22px 0 0 auto; width: 300px; border-top: 2px solid #1d4ed8; padding-top: 12px; }
    .total-row { display: flex; justify-content: space-between; gap: 20px; font-size: 13pt; font-weight: 700; }
    .footer { margin-top: 32px; color: #64748b; font-size: 8pt; border-top: 1px solid #cbd5e1; padding-top: 8px; }
    @media screen { body { padding: 20px; } }
  </style>
</head>
<body>
  <div class="topline">
    <h1>PONUDA</h1>
    <div class="quote-number">${escapeHtml(quote.brojPonude || "-")}</div>
  </div>
  <div class="meta">
    <div class="meta-block">
      <div class="label">Kupac</div>
      <div class="value">${escapeHtml(quote.kupacNaziv || "-")}</div>
    </div>
    ${String(quote.brojUpitaKupca || "").trim() ? `<div class="meta-block"><div class="label">Broj upita kupca</div><div class="value">${escapeHtml(String(quote.brojUpitaKupca).trim())}</div></div>` : ""}
    <div class="meta-block">
      <div class="label">Datum ponude</div>
      <div class="value">${escapeHtml(formatDate(quote.datum))}</div>
    </div>
    <div class="meta-block">
      <div class="label">Broj pozicija</div>
      <div class="value">${(quote.positions || []).length}</div>
    </div>
  </div>
  <table>
    <thead><tr>
      <th>Br.</th><th>Šifra</th><th>Naziv</th><th>Required Qty</th><th>Price Scale Qty</th><th>Cijena / kom (€)</th><th>Ukupno za količinu (€)</th>
    </tr></thead>
    <tbody>${rows || '<tr><td colspan="7">Ponuda nema pozicija.</td></tr>'}</tbody>
  </table>
  ${hasStaffelPrices
    ? '<div class="footer"><strong>Napomena:</strong> Navedene količine su alternativni cjenovni nivoi za isti artikal. Ukupne vrijednosti različitih količina se ne sabiraju.</div>'
    : `<div class="totals"><div class="total-row"><span>Ukupna vrijednost</span><span>${escapeHtml(formatMoney(Number(quote.totalSellingPrice) || 0))}</span></div></div>`}
  <div class="footer">Cijene su izražene u EUR.</div>
  <script>window.addEventListener('load', function () { window.focus(); setTimeout(function () { window.print(); }, 250); });</script>
</body>
</html>`

  printWindow.document.open()
  printWindow.document.write(html)
  printWindow.document.close()
}


/*
 * ==========================================
 * STIL ZAGLAVLJA TABELE
 * ==========================================
 */

  

/**
 * Dodatna TG marža se obračunava samo na kopiji postojeće ponude.
 * Originalni zapis i cijene u Firestoreu ostaju nepromijenjeni.
 */
function applyAdditionalMarkupForPdfTg(quote: Quote, markupPercent: number): Quote {
  const factor = 1 + markupPercent / 100
  const roundMoney = (value: number) => Math.round((value + Number.EPSILON) * 100) / 100

  const positions = (quote.positions || []).map((position: any) => {
    const quantity = Math.max(0, Number(position.quantity) || 0)
    const sellingPricePerPiece = roundMoney((Number(position.sellingPricePerPiece) || 0) * factor)
    const staffelpreise = Array.isArray(position.staffelpreise)
      ? position.staffelpreise.map((tier: any) => {
          const tierQuantity = Math.max(0, Number(tier.quantity) || 0)
          const tierSellingPricePerPiece = roundMoney((Number(tier.sellingPricePerPiece) || 0) * factor)
          return {
            ...tier,
            sellingPricePerPiece: tierSellingPricePerPiece,
            totalSellingPrice: roundMoney(tierSellingPricePerPiece * tierQuantity),
          }
        })
      : position.staffelpreise

    return {
      ...position,
      sellingPricePerPiece,
      totalSellingPrice: roundMoney(sellingPricePerPiece * quantity),
      staffelpreise,
    }
  })

  const totalSellingPrice = roundMoney(
    positions.reduce((sum: number, position: any) => sum + (Number(position.totalSellingPrice) || 0), 0),
  )

  return {
    ...quote,
    positions,
    totalSellingPrice,
  }
}

function formatTgMoney(value: number): string {
  return new Intl.NumberFormat("de-DE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value) || 0)
}

function writeTgQuotePdf(
  printWindow: Window,
  quote: Quote,
  customer?: Customer,
): void {
  const hasStaffelPrices = Boolean(
    (quote as any).staffelpreiseAktivno ||
    (quote.positions || []).some((position: any) =>
      Array.isArray(position.staffelpreise) && position.staffelpreise.length > 0,
    ),
  )

  const rows = (quote.positions || []).flatMap((position: any, index) => {
    const ident = position.code || position.positionNumber || String(index + 1).padStart(3, "0")
    const name = position.name || "-"
    const tiers = Array.isArray(position.staffelpreise) ? position.staffelpreise : []
    const priceRows = hasStaffelPrices && tiers.length > 0
      ? tiers.map((tier: any) => ({
          quantity: Number(tier.quantity) || 0,
          price: Number(tier.sellingPricePerPiece) || 0,
          total: Number(tier.totalSellingPrice) || 0,
        }))
      : [{
          quantity: Number(position.quantity) || 0,
          price: Number(position.sellingPricePerPiece) || 0,
          total: Number(position.totalSellingPrice) || 0,
        }]

    return priceRows.map((priceRow: { quantity: number; price: number; total: number }) => `
      <tr>
        <td class="ident">${escapeHtml(ident)}</td>
        <td class="description">${escapeHtml(name)}</td>
        <td class="quantity">${escapeHtml(priceRow.quantity.toLocaleString("de-DE"))}</td>
        <td class="unit">Stk</td>
        <td class="money">${escapeHtml(formatTgMoney(priceRow.price))} €</td>
        <td class="money">${escapeHtml(formatTgMoney(priceRow.total))} €</td>
      </tr>
    `)
  }).join("")

  const customerData = (customer || {}) as Customer & Record<string, unknown>
  const customerName = String(customerData.naziv || quote.kupacNaziv || "-").trim()
  const postalCode = String(
    customerData.postanskiBroj || customerData.postanskiBrojKupca || customerData.plz || customerData.zip || "",
  ).trim()
  const cityLine = [postalCode, String(customerData.grad || "").trim()]
    .filter(Boolean)
    .join(" ")
  const customerLines = [
    customerName,
    String(customerData.kontaktOsoba || "").trim(),
    String(customerData.adresa || "").trim(),
    cityLine,
    String(customerData.drzava || "").trim(),
  ].filter(Boolean)

  const offerReference = String(
    (quote as any).brojUpitaKupca || quote.brojPonude || quote.id || "-",
  ).trim()
  const quoteDate = formatDate(quote.datum)
  const totalSellingPrice = (quote.positions || []).reduce(
    (sum: number, position: any) => sum + (Number(position.totalSellingPrice) || 0),
    0,
  )
  const logoUrl = new URL("../assets/trendy-logo.png", import.meta.url).href

  const html = `<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Angebot ${escapeHtml(offerReference)}</title>
  <style>
    @page { size: A4 portrait; margin: 12mm 13mm 14mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
      color: #111;
      background: #fff;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 9pt;
      line-height: 1.25;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .document { width: 100%; }
    .letterhead {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      min-height: 39mm;
      margin-bottom: 10mm;
    }
    .logo { display: block; width: 36mm; height: 33mm; object-fit: contain; object-position: left top; }
    .seller-address { min-width: 58mm; padding-top: 1mm; font-size: 9pt; line-height: 1.35; white-space: nowrap; }
    .recipient-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12mm; min-height: 30mm; margin-bottom: 7mm; }
    .recipient-box { width: 58mm; min-height: 30mm; padding: 1.5mm; border: 0.45mm solid #222; font-size: 9pt; line-height: 1.35; overflow-wrap: anywhere; }
    .recipient-box div { min-height: 4mm; }
    .offer-date { min-width: 55mm; padding-top: 1mm; text-align: center; font-size: 11pt; font-weight: 700; white-space: nowrap; }
    .offer-title { margin: 0 0 18mm 1mm; font-size: 19pt; line-height: 1.2; font-weight: 700; letter-spacing: -0.25pt; }
    .offer-title span { margin-left: 3mm; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; break-inside: avoid; }
    th, td { border: 0.35mm solid #111; padding: 1.05mm 0.9mm; vertical-align: middle; font-size: 8.6pt; line-height: 1.15; }
    th { background: #c4c4c4; text-align: left; font-size: 8.8pt; font-weight: 700; }
    .col-ident { width: 17%; } .col-name { width: 41%; } .col-quantity { width: 9%; } .col-unit { width: 8%; } .col-price { width: 12%; } .col-total { width: 13%; }
    td.ident, td.description { overflow-wrap: anywhere; }
    td.quantity, th.quantity { text-align: right; white-space: nowrap; }
    td.unit, th.unit { text-align: center; white-space: nowrap; }
    td.money, th.money { text-align: right; white-space: nowrap; }
    .sum-row td { border-left: 0; border-right: 0; border-bottom: 0; border-top: 0; padding-top: 2mm; font-size: 10pt; font-weight: 700; }
    .sum-row .sum-label { text-align: right; } .sum-row .sum-value { text-align: right; white-space: nowrap; }
    .staffel-note { margin: 4mm 0 0; font-size: 8pt; line-height: 1.4; }
    .closing { margin-top: 22mm; font-size: 8.5pt; line-height: 1.4; }
    .closing p { margin: 0 0 5mm; } .signature { margin-top: 0; }
    @media screen { body { padding: 14mm; } .document { max-width: 190mm; margin: 0 auto; } }
  </style>
</head>
<body>
  <main class="document">
    <header class="letterhead">
      <img class="logo" src="${escapeHtml(logoUrl)}" alt="Trendy Germany">
      <div class="seller-address">
        <div>Trendy Germany GmbH</div>
        <div>Kaiserstrasse 150</div>
        <div>51643 Gummersbach</div>
        <div>Tel: +49 (0)2261 5909949</div>
        <div>e-mail: info@trendy-germany.de</div>
      </div>
    </header>
    <section class="recipient-row">
      <div class="recipient-box">${customerLines.map((line) => `<div>${escapeHtml(line)}</div>`).join("")}</div>
      <div class="offer-date">Gummersbach, ${escapeHtml(quoteDate)}</div>
    </section>
    <h1 class="offer-title">Angebot:<span>${escapeHtml(offerReference)}</span></h1>
    <table aria-label="Angebotspositionen">
      <colgroup><col class="col-ident"><col class="col-name"><col class="col-quantity"><col class="col-unit"><col class="col-price"><col class="col-total"></colgroup>
      <thead><tr><th>Ident</th><th>Name</th><th class="quantity">Menge</th><th class="unit">Einheit</th><th class="money">Preis</th><th class="money">Total</th></tr></thead>
      <tbody>${rows || '<tr><td colspan="6">Keine Positionen vorhanden.</td></tr>'}</tbody>
      ${hasStaffelPrices ? "" : `<tfoot><tr class="sum-row"><td colspan="5" class="sum-label">Summe EUR</td><td class="sum-value">${escapeHtml(formatTgMoney(totalSellingPrice))} €</td></tr></tfoot>`}
    </table>
    ${hasStaffelPrices ? '<p class="staffel-note">Hinweis: Die angegebenen Mengenstaffeln sind alternative Preisstufen für denselben Artikel. Die Gesamtbeträge der unterschiedlichen Mengen werden nicht addiert.</p>' : ""}
    <footer class="closing">
      <p>Bei Rückfragen stehen wir selbstverständlich jederzeit gerne zur Verfügung.</p>
      <p>Mit freundlichen Grüßen,</p>
      <p class="signature">Edvin Čakić.</p>
    </footer>
  </main>
  <script>window.addEventListener('load', function () { window.focus(); setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>`

  printWindow.document.open()
  printWindow.document.write(html)
  printWindow.document.close()
}


/*
 * ==========================================
 * FORMAT DATUMA
 * ==========================================
 *
 * Firebase može vratiti:
 *
 * - Timestamp
 * - Date
 * - string
 *
 * Uvijek prikazujemo:
 *
 * DD.MM.YYYY
 */
function formatDate(
  value: any,
) {
  /*
   * Ako datum ne postoji,
   * prikazujemo "-".
   */
  if (!value) {
    return "-"
  }

  try {
    /*
     * Firebase Timestamp ima toDate().
     *
     * Ako je već Date ili string,
     * pretvaramo ga u Date.
     */
    const date =
      value?.toDate
        ? value.toDate()
        : new Date(value)

    /*
     * Provjera datuma.
     */
    if (
      Number.isNaN(
        date.getTime(),
      )
    ) {
      return "-"
    }

    /*
     * Dan.
     */
    const day =
      String(
        date.getDate(),
      ).padStart(2, "0")

    /*
     * Mjesec.
     */
    const month =
      String(
        date.getMonth() + 1,
      ).padStart(2, "0")

    /*
     * Godina.
     */
    const year =
      date.getFullYear()

    return `${day}.${month}.${year}`
  } catch {
    return "-"
  }
}

/*
 * ==========================================
 * DATUM BEZ VREMENA
 * ==========================================
 *
 * Za filter nam je bitan samo:
 *
 * dan
 * mjesec
 * godina
 *
 * Vrijeme ignorišemo.
 */
function getDateOnly(
  value: any,
) {
  if (!value) {
    return null
  }

  try {
    const date =
      value?.toDate
        ? value.toDate()
        : new Date(value)

    if (
      Number.isNaN(
        date.getTime(),
      )
    ) {
      return null
    }

    return new Date(
      date.getFullYear(),
      date.getMonth(),
      date.getDate(),
    )
  } catch {
    return null
  }
}

function getDateTimeMillis(value: any): number {
  if (!value) return 0

  try {
    const date = value?.toDate ? value.toDate() : new Date(value)
    const millis = date.getTime()
    return Number.isFinite(millis) ? millis : 0
  } catch {
    return 0
  }
}

function formatPreparationDuration(durationMs: number): string {
  const totalSeconds = Math.max(0, Math.round((Number(durationMs) || 0) / 1000))
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  if (hours > 0) return `${hours} h ${minutes} min ${seconds} s`
  if (minutes > 0) return `${minutes} min ${seconds} s`
  return `${seconds} s`
}

function dateToInputValue(value: any): string {
  const date = getDateOnly(value)
  if (!date) return ""
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, "0")
  const day = String(date.getDate()).padStart(2, "0")
  return `${year}-${month}-${day}`
}

function inputValueToDate(value: string): Date | null {
  if (!value) return null
  const [year, month, day] = value.split("-").map(Number)
  if (!year || !month || !day) return null
  const date = new Date(year, month - 1, day)
  return Number.isNaN(date.getTime()) ? null : date
}

function getFollowUpStatusName(status?: FollowUpStatus): string {
  switch (status || "waiting") {
    case "contact": return "Kontaktirati kupca"
    case "completed": return "Završeno"
    default: return "Čekamo odgovor"
  }
}

function getContactChannelName(channel?: string): string {
  switch (channel) {
    case "telefon": return "Telefon"
    case "email": return "E-mail"
    case "sastanak": return "Sastanak"
    default: return "Drugo"
  }
}

function getContactOutcomeName(outcome?: string): string {
  switch (outcome) {
    case "requested_change": return "Traži izmjene"
    case "requested_info": return "Traži dodatne informacije"
    case "negotiation": return "Pregovori"
    case "positive": return "Pozitivan odgovor"
    case "negative": return "Negativan odgovor"
    case "other": return "Drugo"
    default: return "Nema odgovora"
  }
}

function getFollowUpQuoteOutcomeName(status?: string): string {
  switch (status) {
    case "accepted": return "Prihvaćena"
    case "rejected": return "Odbijena"
    default: return "Poslana / čeka odgovor"
  }
}

/**
 * Explicit tracking choice. For older documents, keep a quote in tracking
 * only if the user had previously saved actual follow-up details.
 */
function isQuoteMarkedForFollowUp(quote: QuoteWithCustomer): boolean {
  if (typeof quote.pratiPonudu === "boolean") {
    return quote.pratiPonudu
  }

  return Boolean(
    quote.datumSlanja ||
    quote.datumSljedecegKontakta ||
    String(quote.napomenaPracenja || "").trim(),
  )
}

function isQuoteActiveForFollowUp(quote: QuoteWithCustomer): boolean {
  return quote.status === "sent"
    && isQuoteMarkedForFollowUp(quote)
    && (quote.statusPracenja || "waiting") !== "completed"
}

function getFollowUpDue(value: any): { label: string; className: string } {
  const targetDate = getDateOnly(value)
  if (!targetDate) return { label: "Nije planirano", className: "followup-due-none" }
  const today = getDateOnly(new Date())
  if (!today) return { label: formatDate(value), className: "followup-due-upcoming" }
  if (targetDate.getTime() < today.getTime()) {
    return { label: "Kasni", className: "followup-due-overdue" }
  }
  if (targetDate.getTime() === today.getTime()) {
    return { label: "Danas", className: "followup-due-today" }
  }
  return { label: "Planirano", className: "followup-due-upcoming" }
}

/*
 * ==========================================
 * FORMAT NOVCA
 * ==========================================
 */
function formatMoney(
  value: number,
) {
  return `${Number(
    value || 0,
  ).toFixed(2)} €`
}

/*
 * ==========================================
 * NAZIV STATUSA
 * ==========================================
 */
function getStatusName(
  status: string,
) {
  switch (status) {
    case "draft":
      return "Nacrt"

    case "sent":
      return "Poslana"

    case "accepted":
      return "Prihvaćena"

    case "rejected":
      return "Odbijena"

    default:
      return status || "-"
  }
}

/*
 * ==========================================
 * CSS KLASA STATUSA
 * ==========================================
 */
function getStatusClass(
  status: string,
) {
  switch (status) {
    case "accepted":
      return "status accepted"

    case "sent":
      return "status sent"

    case "rejected":
      return "status rejected"

    default:
      return "status draft"
  }
}

type DashboardPeriod = "all" | "thisMonth" | "lastMonth" | "thisYear" | "custom"

type DashboardDateRange = {
  from: Date | null
  to: Date | null
  label: string
  invalid: boolean
}

function formatDashboardDate(value: Date | null): string {
  if (!value) return ""
  return `${String(value.getDate()).padStart(2, "0")}.${String(value.getMonth() + 1).padStart(2, "0")}.${value.getFullYear()}`
}

function getDashboardDateRange(
  period: DashboardPeriod,
  customFrom: Date | null,
  customTo: Date | null,
  now = new Date(),
): DashboardDateRange {
  const startOfDay = (year: number, month: number, day: number) =>
    new Date(year, month, day)
  const endOfDay = (year: number, month: number, day: number) =>
    new Date(year, month, day, 23, 59, 59, 999)

  if (period === "all") {
    return { from: null, to: null, label: "Svi datumi", invalid: false }
  }

  if (period === "thisMonth") {
    const from = startOfDay(now.getFullYear(), now.getMonth(), 1)
    const to = endOfDay(now.getFullYear(), now.getMonth() + 1, 0)
    const label = now.toLocaleDateString("bs-BA", { month: "long", year: "numeric" })
    return { from, to, label, invalid: false }
  }

  if (period === "lastMonth") {
    const from = startOfDay(now.getFullYear(), now.getMonth() - 1, 1)
    const to = endOfDay(now.getFullYear(), now.getMonth(), 0)
    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1)
    const label = lastMonth.toLocaleDateString("bs-BA", { month: "long", year: "numeric" })
    return { from, to, label, invalid: false }
  }

  if (period === "thisYear") {
    return {
      from: startOfDay(now.getFullYear(), 0, 1),
      to: endOfDay(now.getFullYear(), 11, 31),
      label: String(now.getFullYear()),
      invalid: false,
    }
  }

  const from = customFrom ? getDateOnly(customFrom) : null
  const to = customTo ? endOfDay(customTo.getFullYear(), customTo.getMonth(), customTo.getDate()) : null
  const invalid = Boolean(from && customTo && to && from.getTime() > to.getTime())
  const label = from || to
    ? `${from ? formatDashboardDate(from) : "Početak nije odabran"} – ${to ? formatDashboardDate(customTo) : "bez krajnjeg datuma"}`
    : "Odaberi datumski raspon"

  return { from, to, label, invalid }
}

/*
 * ==========================================
 * GLAVNA KOMPONENTA
 * ==========================================
 */
type QuotesView = "offers" | "analysis"

type QuotesProps = {
  view?: QuotesView
}

function Quotes({ view = "offers" }: QuotesProps) {
  const { profile } = useAuth()
  const canCreateQuote = hasPermission(profile, "quotes", "create")
  const canUpdateQuote = hasPermission(profile, "quotes", "update")
  const canDeleteQuote = hasPermission(profile, "quotes", "delete")



  /*
   * ==========================================
   * SVE PONUDE
   * ==========================================
   */
  const [
    quotes,
    setQuotes,
  ] = useState<
    QuoteWithCustomer[]
  >([])



  /*
   * ==========================================
   * UČITAVANJE
   * ==========================================
   */
  const [
    loading,
    setLoading,
  ] = useState(true)

  /*
   * ==========================================
   * TEKSTUALNA PRETRAGA
   * ==========================================
   */
  const [
    search,
    setSearch,
  ] = useState("")

  // Paginacija liste ponuda: broj redova po stranici i aktivna stranica.
  const [pageSize, setPageSize] = useState<number>(10)
  const [currentPage, setCurrentPage] = useState(1)

  /*
   * ==========================================
   * FILTER STATUSA
   * ==========================================
   */
  const [
    statusFilter,
    setStatusFilter,
  ] = useState<StatusFilter>(
    "all",
  )

  /*
   * ==========================================
   * SORTIRANJE PONUDA
   * ==========================================
   *
   * Početno sortiranje je:
   * Najnovije prvo.
   */
  const [
    sortOption,
    setSortOption,
  ] = useState<SortOption>(
    "newest",
  )

  /*
   * ==========================================
   * FILTER DATUM - OD
   * ==========================================
   *
   * Sada koristimo Date | null jer
   * react-datepicker vraća Date objekat.
   *
   * null znači da datum nije odabran.
   */
  const [
    dateFrom,
    setDateFrom,
  ] = useState<Date | null>(
    null,
  )

  /*
   * ==========================================
   * FILTER DATUM - DO
   * ==========================================
   */
  const [
    dateTo,
    setDateTo,
  ] = useState<Date | null>(
    null,
  )

  /*
   * ==========================================
   * OTVORENA PONUDA
   * ==========================================
   */
  const [
    selectedQuote,
    setSelectedQuote,
  ] = useState<
    QuoteWithCustomer | null
  >(null)

  // Export PDF TG za postojeću ponudu; dodatna marža nikada ne mijenja bazni zapis.
  const [showTgExportModal, setShowTgExportModal] = useState(false)
  const [tgMarkupPercentText, setTgMarkupPercentText] = useState("")
  const [tgExportError, setTgExportError] = useState("")
  const [tgExporting, setTgExporting] = useState(false)

  // Odabrani kupac za pregled kompletne historije saradnje.
  const [selectedCustomerHistoryKey, setSelectedCustomerHistoryKey] =
    useState<string | null>(null)

  const [deletingQuoteId, setDeletingQuoteId] = useState<string | null>(null)
  const [savingFollowUpQuoteId, setSavingFollowUpQuoteId] = useState<string | null>(null)

  const [followUpForm, setFollowUpForm] = useState({
    datumSlanja: "",
    datumSljedecegKontakta: "",
    statusPracenja: "waiting" as FollowUpStatus,
    ishodPonude: "sent" as FollowUpQuoteOutcome,
    napomenaPracenja: "",
  })
  const [savingFollowUp, setSavingFollowUp] = useState(false)
  const [followUpMessage, setFollowUpMessage] = useState("")

  const [contactForm, setContactForm] = useState({
    datum: dateToInputValue(new Date()),
    kanal: "email" as ContactChannel,
    kontaktOsoba: "",
    ishod: "no_response" as ContactOutcome,
    napomena: "",
  })
  const [savingContact, setSavingContact] = useState(false)
  const [contactMessage, setContactMessage] = useState("")

  useEffect(() => {
    if (!selectedQuote) return
    setFollowUpForm({
      datumSlanja: dateToInputValue(selectedQuote.datumSlanja),
      datumSljedecegKontakta: dateToInputValue(selectedQuote.datumSljedecegKontakta),
      statusPracenja: selectedQuote.statusPracenja || "waiting",
      ishodPonude: selectedQuote.status === "accepted" || selectedQuote.status === "rejected"
        ? selectedQuote.status
        : "sent",
      napomenaPracenja: selectedQuote.napomenaPracenja || "",
    })
    setContactForm({
      datum: dateToInputValue(new Date()),
      kanal: "email",
      kontaktOsoba: "",
      ishod: "no_response",
      napomena: "",
    })
    setFollowUpMessage("")
    setContactMessage("")
  }, [selectedQuote?.id])

  function openTgExportModal() {
    if (!selectedQuote) return
    setTgMarkupPercentText("")
    setTgExportError("")
    setShowTgExportModal(true)
  }

  async function handleExportPdfTg(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!selectedQuote || tgExporting) return

    const trimmedMarkup = tgMarkupPercentText.trim()
    const markupPercent = trimmedMarkup === "" ? null : parseLocalizedNumber(trimmedMarkup)
    if (markupPercent === null || !Number.isFinite(markupPercent) || markupPercent < 0) {
      setTgExportError("Unesi ispravnu maržu od 0% ili više. Možeš koristiti decimalni zarez.")
      return
    }

    // Otvori prozor prije await-a kako preglednik ne bi blokirao PDF.
    const printWindow = window.open("", "_blank", "width=1050,height=800")
    if (!printWindow) {
      setTgExportError("Pregled PDF-a je blokiran. Dozvoli iskačuće prozore za ovu aplikaciju i pokušaj ponovo.")
      return
    }

    const quoteForExport = selectedQuote
    printWindow.document.write("<!doctype html><html lang='de'><body style='font-family:Arial;padding:24px'>Angebot wird vorbereitet...</body></html>")
    printWindow.document.close()
    setShowTgExportModal(false)
    setTgExporting(true)

    try {
      let customer: Customer | undefined
      try {
        const customers = await getCustomers()
        customer = customers.find((item) => item.id && item.id === quoteForExport.kupacId)
          || customers.find((item) => item.naziv.trim().toLocaleLowerCase() === String(quoteForExport.kupacNaziv || "").trim().toLocaleLowerCase())
      } catch (customerError) {
        // PDF se i dalje može generisati sa nazivom kupca koji je sačuvan u ponudi.
        console.warn("Nije moguće učitati adresu kupca za PDF TG:", customerError)
      }

      const tgQuote = applyAdditionalMarkupForPdfTg(quoteForExport, markupPercent)
      writeTgQuotePdf(printWindow, tgQuote, customer)
    } catch (error) {
      console.error("Greška pri izvozu postojeće ponude u PDF TG:", error)
      printWindow.close()
      window.alert("Izvoz PDF TG nije uspio. Originalna ponuda nije izmijenjena.")
    } finally {
      setTgExporting(false)
    }
  }

  const followUpQuotes = useMemo(() => {
    return quotes
      .filter(isQuoteActiveForFollowUp)
      .sort((a, b) => {
        const dateA = getDateOnly(a.datumSljedecegKontakta)
        const dateB = getDateOnly(b.datumSljedecegKontakta)
        if (!dateA && !dateB) return 0
        if (!dateA) return 1
        if (!dateB) return -1
        return dateA.getTime() - dateB.getTime()
      })
  }, [quotes])

  const selectedCustomerHistory = useMemo(() => {
    if (!selectedCustomerHistoryKey) return null

    // Historija obuhvata sve datume, ne samo trenutno odabrani
    // period na kontrolnoj tabli.
    const historyQuotes = quotes
      .filter((quote) => getCustomerGroupingKey(quote) === selectedCustomerHistoryKey)
      .sort((a, b) => {
        const dateA = getDateOnly(a.datum)?.getTime() ?? 0
        const dateB = getDateOnly(b.datum)?.getTime() ?? 0
        return dateB - dateA
      })

    if (historyQuotes.length === 0) return null

    const acceptedQuotes = historyQuotes.filter(
      (quote) => getDashboardStatus(quote) === "accepted",
    )
    const rejectedQuotes = historyQuotes.filter(
      (quote) => getDashboardStatus(quote) === "rejected",
    )
    const decidedQuotes = acceptedQuotes.length + rejectedQuotes.length
    const offeredValue = historyQuotes.reduce(
      (sum, quote) => sum + getQuoteFinance(quote).sales,
      0,
    )
    const acceptedValue = acceptedQuotes.reduce(
      (sum, quote) => sum + getQuoteFinance(quote).sales,
      0,
    )
    const latestNamedQuote = historyQuotes.find(
      (quote) => String(quote.kupacNaziv || "").trim(),
    )

    return {
      key: selectedCustomerHistoryKey,
      customerName: String(latestNamedQuote?.kupacNaziv || "").trim()
        || (selectedCustomerHistoryKey === "unassigned-customer" ? "Kupac nije naveden" : "Kupac"),
      quotes: historyQuotes,
      quoteCount: historyQuotes.length,
      acceptedCount: acceptedQuotes.length,
      rejectedCount: rejectedQuotes.length,
      offeredValue,
      acceptedValue,
      successRate: decidedQuotes > 0 ? (acceptedQuotes.length / decidedQuotes) * 100 : null,
      isUnassigned: selectedCustomerHistoryKey === "unassigned-customer",
    }
  }, [selectedCustomerHistoryKey, quotes])

  const saveFollowUp = async () => {
    if (!selectedQuote?.id) {
      setFollowUpMessage("Nije pronađen ID ponude.")
      return
    }

    try {
      setSavingFollowUp(true)
      setFollowUpMessage("")

      const isDecisionRecorded = followUpForm.ishodPonude !== "sent"
      const nextActivityStatus: FollowUpStatus = isDecisionRecorded
        ? "completed"
        : followUpForm.statusPracenja

      const updateData = {
        // Status ponude je odvojen od statusa aktivnosti praćenja.
        status: followUpForm.ishodPonude,
        datumSlanja: inputValueToDate(followUpForm.datumSlanja),
        datumSljedecegKontakta: inputValueToDate(followUpForm.datumSljedecegKontakta),
        statusPracenja: nextActivityStatus,
        napomenaPracenja: followUpForm.napomenaPracenja.trim(),
        // Zaključene ponude ne ostaju na listi otvorenog praćenja.
        pratiPonudu: !isDecisionRecorded && nextActivityStatus !== "completed",
      }

      await updateQuote(selectedQuote.id, updateData as any)

      const updatedQuote: QuoteWithCustomer = {
        ...selectedQuote,
        ...updateData,
        updatedAt: new Date(),
      }

      setQuotes((current) =>
        current.map((quote) => quote.id === updatedQuote.id ? updatedQuote : quote),
      )
      setSelectedQuote(updatedQuote)
      setFollowUpMessage("Podaci za praćenje ponude su sačuvani.")
    } catch (error) {
      console.error("Greška pri spremanju praćenja ponude:", error)
      setFollowUpMessage("Nije moguće sačuvati podatke. Pokušaj ponovo.")
    } finally {
      setSavingFollowUp(false)
    }
  }


  const saveContactHistoryEntry = async () => {
    if (!selectedQuote?.id) {
      setContactMessage("Nije pronađen ID ponude.")
      return
    }

    const contactDate = inputValueToDate(contactForm.datum)
    if (!contactDate) {
      setContactMessage("Unesi ispravan datum kontakta.")
      return
    }

    if (!contactForm.napomena.trim() && !contactForm.kontaktOsoba.trim()) {
      setContactMessage("Unesi ime kontakt osobe ili kratku bilješku o razgovoru.")
      return
    }

    const entry: QuoteContactHistoryEntry = {
      id: `contact-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      datum: contactDate,
      kanal: contactForm.kanal,
      kontaktOsoba: contactForm.kontaktOsoba.trim(),
      ishod: contactForm.ishod,
      napomena: contactForm.napomena.trim(),
      evidentirao: profile?.imePrezime?.trim() || profile?.email?.trim() || selectedQuote.createdBy || "Korisnik",
      evidentiranoU: new Date(),
    }

    try {
      setSavingContact(true)
      setContactMessage("")
      await addQuoteContactHistory(selectedQuote.id, entry)

      const updatedQuote: QuoteWithCustomer = {
        ...selectedQuote,
        historijaKontakata: [...(selectedQuote.historijaKontakata || []), entry],
        updatedAt: new Date(),
      }

      setQuotes((current) => current.map((quote) =>
        quote.id === updatedQuote.id ? updatedQuote : quote,
      ))
      setSelectedQuote(updatedQuote)
      setContactForm((current) => ({
        ...current,
        datum: dateToInputValue(new Date()),
        kontaktOsoba: "",
        ishod: "no_response",
        napomena: "",
      }))
      setContactMessage("Kontakt je sačuvan u historiju.")
    } catch (error) {
      console.error("Greška pri spremanju historije kontakata:", error)
      setContactMessage("Kontakt nije sačuvan. Provjeri vezu i pokušaj ponovo.")
    } finally {
      setSavingContact(false)
    }
  }

  const handleToggleQuoteFollowUp = async (quote: QuoteWithCustomer) => {
    if (!quote.id) {
      window.alert("Nije moguće mijenjati praćenje jer ponuda nema ID.")
      return
    }

    if (quote.status !== "draft" && quote.status !== "sent") {
      window.alert("Praćenje je namijenjeno ponudama koje su poslane kupcu, ali još nisu zaključene.")
      return
    }

    const isActive = isQuoteActiveForFollowUp(quote)
    let updateData: Record<string, unknown>

    if (quote.status === "draft") {
      const confirmed = window.confirm(
        `Želiš li označiti ponudu ${quote.brojPonude || quote.id} kao poslanu i dodati je u praćenje?\n\n` +
        "Status ponude promijenit će se iz 'Nacrt' u 'Poslana', a datum slanja bit će postavljen na današnji dan ako već nije evidentiran.",
      )
      if (!confirmed) return

      updateData = {
        status: "sent",
        pratiPonudu: true,
        datumSlanja: quote.datumSlanja || new Date(),
        statusPracenja: "waiting",
      }
    } else if (isActive) {
      const confirmed = window.confirm(
        `Ukloniti ponudu ${quote.brojPonude || quote.id} iz liste za praćenje?\n\nStatus ponude će ostati 'Poslana'.`,
      )
      if (!confirmed) return

      updateData = { pratiPonudu: false }
    } else {
      updateData = {
        pratiPonudu: true,
        datumSlanja: quote.datumSlanja || new Date(),
        statusPracenja: quote.statusPracenja === "completed"
          ? "waiting"
          : quote.statusPracenja || "waiting",
      }
    }

    try {
      setSavingFollowUpQuoteId(quote.id)
      await updateQuote(quote.id, updateData as any)

      const updatedQuote: QuoteWithCustomer = {
        ...quote,
        ...updateData,
        updatedAt: new Date(),
      } as QuoteWithCustomer

      setQuotes((current) =>
        current.map((item) => item.id === updatedQuote.id ? updatedQuote : item),
      )
      setSelectedQuote((current) =>
        current?.id === updatedQuote.id ? { ...current, ...updatedQuote } : current,
      )
    } catch (error) {
      console.error("Greška pri promjeni praćenja ponude:", error)
      window.alert("Nije moguće promijeniti praćenje ponude. Provjeri vezu i pokušaj ponovo.")
    } finally {
      setSavingFollowUpQuoteId(null)
    }
  }

  const handleCopyQuote = (quote: QuoteWithCustomer) => {
    if (!quote.id) {
      window.alert("Nije moguće kopirati ponudu jer nedostaje njen ID.")
      return
    }

    // NewQuote.tsx koristi ?copy=<ID> da učita podatke kao novu, zasebnu ponudu.
    const copyUrl = new URL("/new-quote", window.location.origin)
    copyUrl.searchParams.set("copy", quote.id)
    window.location.assign(`${copyUrl.pathname}${copyUrl.search}`)
  }

  const handleDeleteQuote = async (quote: QuoteWithCustomer) => {
    if (!quote.id) {
      window.alert("Nije moguće obrisati ponudu jer nedostaje njen ID.")
      return
    }

    const quoteLabel = quote.brojPonude || quote.id
    const customerLabel = quote.kupacNaziv ? ` za kupca ${quote.kupacNaziv}` : ""
    const confirmed = window.confirm(
      `Jeste li sigurni da želite trajno obrisati ponudu ${quoteLabel}${customerLabel}?

` +
      "Ova radnja se ne može poništiti. Tehnički podaci pozicija u bazi pozicija ostat će sačuvani.",
    )

    if (!confirmed) return

    try {
      setDeletingQuoteId(quote.id)
      await deleteQuoteFromDatabase(quote.id)

      setQuotes((current) => current.filter((item) => item.id !== quote.id))
      setSelectedQuote((current) => current?.id === quote.id ? null : current)
      setFollowUpMessage("")
      window.alert(`Ponuda ${quoteLabel} je uspješno obrisana.`)
    } catch (error) {
      console.error("Greška pri brisanju ponude:", error)
      window.alert(
        `Ponudu ${quoteLabel} nije moguće obrisati. Provjeri internet vezu i korisnička prava, pa pokušaj ponovo.`,
      )
    } finally {
      setDeletingQuoteId(null)
    }
  }

  /*
   * ==========================================
   * UČITAVANJE PONUDA
   * ==========================================
   */
  const loadQuotes =
    async () => {
      try {
        setLoading(true)

        /*
         * Učitavamo ponude iz Firebase-a.
         */
        const data =
          await getAllQuotes()

        /*
         * Spremamo ih u state.
         */
        setQuotes(
          data as QuoteWithCustomer[],
        )
      } catch (error) {

        /*
         * Stvarna greška u konzoli.
         */
        console.error(
          "Greška pri učitavanju ponuda:",
          error,
        )

        /*
         * Poruka korisniku.
         */
        alert(
          "Greška pri učitavanju ponuda iz Firebase-a.",
        )
      } finally {
        setLoading(false)
      }
    }

  /*
   * ==========================================
   * UČITAJ PONUDE PRI OTVARANJU STRANICE
   * ==========================================
   */
  useEffect(() => {
    loadQuotes()
  }, [])

  // Promjena pretrage, filtera, sortiranja ili broja redova vraća listu na prvu stranicu.
  useEffect(() => {
    setCurrentPage(1)
  }, [search, statusFilter, dateFrom, dateTo, sortOption, pageSize])

  /*
   * ==========================================
   * FILTRIRANE PONUDE
   * ==========================================
   *
   * Kombinujemo:
   *
   * 1. tekstualnu pretragu
   * 2. status
   * 3. datum OD
   * 4. datum DO
   */
  const filteredQuotes =
    useMemo(() => {

      /*
       * Tekst pretrage pretvaramo
       * u mala slova.
       */
      const value =
        search
          .trim()
          .toLowerCase()

      /*
       * ==========================================
       * DATUM OD
       * ==========================================
       *
       * Pretvaramo odabrani datum
       * u datum bez vremena.
       */
      const fromDate =
        dateFrom
          ? new Date(
              dateFrom.getFullYear(),
              dateFrom.getMonth(),
              dateFrom.getDate(),
            )
          : null

      /*
       * ==========================================
       * DATUM DO
       * ==========================================
       */
      const toDate =
        dateTo
          ? new Date(
              dateTo.getFullYear(),
              dateTo.getMonth(),
              dateTo.getDate(),
            )
          : null

      /*
       * ==========================================
       * FILTRIRANJE
       * ==========================================
       */
      /*
       * ==========================================
       * PRVO PRIMJENJUJEMO SVE FILTERE
       * ==========================================
       *
       * Tek nakon toga rezultat sortiramo.
       */
      const filtered =
        quotes.filter(
          (quote) => {

          /*
           * ==========================================
           * STATUS
           * ==========================================
           */
          const matchesStatus =
            statusFilter ===
              "all" ||
            quote.status ===
              statusFilter

          /*
           * Ako status ne odgovara,
           * odmah izbacujemo ponudu.
           */
          if (!matchesStatus) {
            return false
          }

          /*
           * ==========================================
           * DATUM PONUDE
           * ==========================================
           */
          const quoteDate =
            getDateOnly(
              quote.datum,
            )

          /*
           * Ako korisnik koristi
           * filter datuma, a ponuda
           * nema ispravan datum,
           * ne prikazujemo je.
           */
          if (
            (fromDate ||
              toDate) &&
            !quoteDate
          ) {
            return false
          }

          /*
           * ==========================================
           * DATUM OD
           * ==========================================
           */
          if (
            fromDate &&
            quoteDate &&
            quoteDate < fromDate
          ) {
            return false
          }

          /*
           * ==========================================
           * DATUM DO
           * ==========================================
           */
          if (
            toDate &&
            quoteDate &&
            quoteDate > toDate
          ) {
            return false
          }

          /*
           * ==========================================
           * TEKSTUALNA PRETRAGA
           * ==========================================
           *
           * VAŽNO:
           *
           * Datum i status su već
           * provjereni.
           *
           * Ako nema tekstualne pretrage,
           * ponuda prolazi.
           */
          if (!value) {
            return true
          }

          /*
           * Broj ponude.
           */
          const quoteNumber =
            quote.brojPonude
              ?.toLowerCase() ||
            ""

          /*
           * Broj upita kupca.
           * Polje je opcionalno radi kompatibilnosti
           * sa starijim ponudama.
           */
          const customerInquiryNumber =
            String(quote.brojUpitaKupca || "")
              .toLowerCase()
              .trim()

          /*
           * Naziv kupca.
           */
          const kupacNaziv =
            quote.kupacNaziv
              ?.toLowerCase() ||
            ""

          /*
           * Korisnik koji je napravio ponudu.
           */
          const createdBy =
            quote.createdBy
              ?.toLowerCase() ||
            ""

          /*
           * Status.
           */
          const status =
            quote.status
              ?.toLowerCase() ||
            ""

          /*
           * ==========================================
           * PRETRAGA PO POZICIJAMA
           * ==========================================
           */
          const positionMatch =
            quote.positions?.some(
              (position) =>
                position.code
                  ?.toLowerCase()
                  .includes(value) ||
                position.name
                  ?.toLowerCase()
                  .includes(value),
            )

          /*
           * Ako tekst postoji u bilo kojem
           * od navedenih polja,
           * ponuda prolazi.
           */
          return (
            quoteNumber.includes(
              value,
            ) ||
            customerInquiryNumber.includes(
              value,
            ) ||
            kupacNaziv.includes(
              value,
            ) ||
            createdBy.includes(
              value,
            ) ||
            status.includes(
              value,
            ) ||
            Boolean(
              positionMatch,
            )
          )
          },
        )

      /*
       * ==========================================
       * SORTIRANJE FILTRIRANIH REZULTATA
       * ==========================================
       *
       * slice() pravi kopiju niza kako originalni
       * quotes state ne bismo mijenjali.
       */
      return filtered
        .slice()
        .sort((a, b) => {

          switch (sortOption) {

            /* Najnovija ponuda prva. */
            case "newest": {
              const dateA =
                getDateOnly(a.datum)

              const dateB =
                getDateOnly(b.datum)

              return (
                (dateB?.getTime() || 0) -
                (dateA?.getTime() || 0)
              )
            }

            /* Najstarija ponuda prva. */
            case "oldest": {
              const dateA =
                getDateOnly(a.datum)

              const dateB =
                getDateOnly(b.datum)

              return (
                (dateA?.getTime() || 0) -
                (dateB?.getTime() || 0)
              )
            }

            /* Broj ponude A-Z. */
            case "quoteAsc":
              return (
                a.brojPonude || ""
              ).localeCompare(
                b.brojPonude || "",
                undefined,
                {
                  numeric: true,
                  sensitivity: "base",
                },
              )

            /* Broj ponude Z-A. */
            case "quoteDesc":
              return (
                b.brojPonude || ""
              ).localeCompare(
                a.brojPonude || "",
                undefined,
                {
                  numeric: true,
                  sensitivity: "base",
                },
              )

            /* Kupac A-Z. */
            case "customerAsc":
              return (
                a.kupacNaziv || ""
              ).localeCompare(
                b.kupacNaziv || "",
                undefined,
                {
                  sensitivity: "base",
                },
              )

            /* Kupac Z-A. */
            case "customerDesc":
              return (
                b.kupacNaziv || ""
              ).localeCompare(
                a.kupacNaziv || "",
                undefined,
                {
                  sensitivity: "base",
                },
              )

            /* Najveća prodajna vrijednost. */
            case "valueDesc":
              return (
                Number(
                  b.totalSellingPrice || 0,
                ) -
                Number(
                  a.totalSellingPrice || 0,
                )
              )

            /* Najmanja prodajna vrijednost. */
            case "valueAsc":
              return (
                Number(
                  a.totalSellingPrice || 0,
                ) -
                Number(
                  b.totalSellingPrice || 0,
                )
              )

            default:
              return 0
          }
        })
    }, [
      quotes,
      search,
      statusFilter,
      dateFrom,
      dateTo,
      sortOption,
    ])

  // Paginacija se primjenjuje tek nakon svih filtera i sortiranja.
  const totalPages = Math.max(1, Math.ceil(filteredQuotes.length / pageSize))
  const safeCurrentPage = Math.min(currentPage, totalPages)
  const firstVisibleQuote = filteredQuotes.length === 0
    ? 0
    : (safeCurrentPage - 1) * pageSize + 1
  const lastVisibleQuote = Math.min(safeCurrentPage * pageSize, filteredQuotes.length)
  const paginatedQuotes = filteredQuotes.slice(
    (safeCurrentPage - 1) * pageSize,
    safeCurrentPage * pageSize,
  )

  const visiblePages = Array.from(
    { length: totalPages },
    (_, index) => index + 1,
  ).filter((page) =>
    page === 1 ||
    page === totalPages ||
    Math.abs(page - safeCurrentPage) <= 1,
  )

  /*
   * ==========================================
   * FILTER PERIODA KONTROLNE TABLE
   * ==========================================
   * Odvojen je od filtera liste ponuda i utiče
   * samo na finansijske pokazatelje iznad.
   */
  const [dashboardPeriod, setDashboardPeriod] = useState<DashboardPeriod>("all")
  const [dashboardDateFrom, setDashboardDateFrom] = useState<Date | null>(null)
  const [dashboardDateTo, setDashboardDateTo] = useState<Date | null>(null)

  /*
   * ==========================================
   * KONTROLNA TABLA PROFITABILNOSTI
   * ==========================================
   * Finansijski zbir se računa iz svih spremljenih
   * ponuda, bez obzira na aktivne filtere pretrage.
   * Historijske/odbijene ponude ostaju uključene u
   * vrijednost svih ponuda, a statusi se prikazuju
   * odvojeno radi jasnog tumačenja.
   */
  const dashboardRange = useMemo(
    () => getDashboardDateRange(
      dashboardPeriod,
      dashboardDateFrom,
      dashboardDateTo,
    ),
    [dashboardPeriod, dashboardDateFrom, dashboardDateTo],
  )

  const dashboardData = useMemo(() => {
    const periodQuotes = quotes.filter((quote) => {
      if (dashboardPeriod === "all") return true

      // U prilagođenom rasponu bez ijednog datuma još ne
      // sužavamo prikaz; čim se izabere barem jedan datum,
      // ponude bez ispravnog datuma ne ulaze u period.
      if (dashboardPeriod === "custom" && !dashboardDateFrom && !dashboardDateTo) {
        return true
      }

      const quoteDate = getDateOnly(quote.datum)
      if (!quoteDate) return false
      if (dashboardRange.from && quoteDate < dashboardRange.from) return false
      if (dashboardRange.to) {
        const end = new Date(dashboardRange.to.getFullYear(), dashboardRange.to.getMonth(), dashboardRange.to.getDate())
        if (quoteDate > end) return false
      }
      return true
    })

    // Trajanje postoji samo za ponude koje su napravljene nakon uvođenja tajmera.
    // Starije ponude se ne računaju kao nula, nego se potpuno izostavljaju iz prosjeka.
    const measuredTimeRows = periodQuotes
      .map((quote) => ({
        quote,
        durationMs: Number(quote.vrijemeIzradePonudeMs),
      }))
      .filter((row) => Number.isFinite(row.durationMs) && row.durationMs > 0)

    const totalPreparationTimeMs = measuredTimeRows.reduce(
      (sum, row) => sum + row.durationMs,
      0,
    )
    const averagePreparationTimeMs = measuredTimeRows.length > 0
      ? totalPreparationTimeMs / measuredTimeRows.length
      : 0
    const shortestPreparationTimeMs = measuredTimeRows.length > 0
      ? Math.min(...measuredTimeRows.map((row) => row.durationMs))
      : 0
    const longestPreparationTimeMs = measuredTimeRows.length > 0
      ? Math.max(...measuredTimeRows.map((row) => row.durationMs))
      : 0

    const preparationTimeByAuthorMap = new Map<string, {
      author: string
      count: number
      totalMs: number
    }>()
    measuredTimeRows.forEach(({ quote, durationMs }) => {
      const author = String(quote.createdBy || "Nepoznat korisnik").trim() || "Nepoznat korisnik"
      const current = preparationTimeByAuthorMap.get(author) || {
        author,
        count: 0,
        totalMs: 0,
      }
      current.count += 1
      current.totalMs += durationMs
      preparationTimeByAuthorMap.set(author, current)
    })

    const preparationTimeByAuthor = Array.from(preparationTimeByAuthorMap.values())
      .map((row) => ({
        ...row,
        averageMs: row.count > 0 ? row.totalMs / row.count : 0,
      }))
      .sort((a, b) => b.averageMs - a.averageMs || a.author.localeCompare(b.author, "bs"))

    const recentPreparationRows = [...measuredTimeRows]
      .sort((a, b) =>
        getDateTimeMillis(b.quote.vrijemeIzradePonudeKraj || b.quote.datum) -
        getDateTimeMillis(a.quote.vrijemeIzradePonudeKraj || a.quote.datum),
      )
      .slice(0, 10)

    const summaryRows = periodQuotes.map((quote) => {
      const finance = getQuoteFinance(quote)
      return {
        quote,
        ...finance,
        profit: finance.sales - finance.cost,
        hasCompleteFinance: finance.sales > 0 && finance.cost > 0,
      }
    })

    const quotesWithFinance = summaryRows.filter(
      (row) => row.hasCompleteFinance,
    )
    const costedSales = quotesWithFinance.reduce(
      (sum, row) => sum + row.sales,
      0,
    )
    const costedCosts = quotesWithFinance.reduce(
      (sum, row) => sum + row.cost,
      0,
    )
    const estimatedProfit = costedSales - costedCosts
    const marginPercent = costedSales > 0
      ? (estimatedProfit / costedSales) * 100
      : 0

    const statusCounts: Record<Exclude<StatusFilter, "all">, number> = {
      draft: 0,
      sent: 0,
      accepted: 0,
      rejected: 0,
    }
    summaryRows.forEach(({ quote }) => {
      statusCounts[getDashboardStatus(quote)] += 1
    })

    const acceptedSales = summaryRows
      .filter(({ quote }) => getDashboardStatus(quote) === "accepted")
      .reduce((sum, row) => sum + row.sales, 0)

    const lowestMarginLines: DashboardLine[] = []
    summaryRows.forEach(({ quote }) => {
      const positions = Array.isArray(quote.positions)
        ? quote.positions as unknown as DashboardPosition[]
        : []
      positions.forEach((position, index) => {
        const finance = getPositionFinance(position)
        // Pozicija se upoređuje samo kada postoje i prodajna
        // cijena i trošak; nepotpune kalkulacije se preskaču.
        if (finance.sellingPerPiece <= 0 || finance.costPerPiece <= 0) {
          return
        }
        const profitPerPiece = finance.sellingPerPiece - finance.costPerPiece
        lowestMarginLines.push({
          key: `${quote.id || quote.brojPonude || "quote"}-${position.code || index}-${index}`,
          code: String(position.code || "—"),
          name: String(position.name || "—"),
          quoteNumber: String(quote.brojPonude || "—"),
          customer: String(quote.kupacNaziv || "—"),
          quantity: finance.quantity,
          costPerPiece: finance.costPerPiece,
          sellingPerPiece: finance.sellingPerPiece,
          profitPerPiece,
          marginPercent: (profitPerPiece / finance.sellingPerPiece) * 100,
        })
      })
    })
    lowestMarginLines.sort((a, b) => a.marginPercent - b.marginPercent)

    // Grupisanje po kupcu. Prednost ima ID kupca; kod starijih
    // ponuda bez ID-a koristimo normalizovani naziv. Ponude bez
    // podataka o kupcu stavljamo u zasebnu grupu da ih ne izgubimo.
    const customerMap = new Map<string, {
      key: string
      customerName: string
      quoteCount: number
      acceptedCount: number
      rejectedCount: number
      offeredValue: number
      acceptedValue: number
      isUnassigned: boolean
    }>()

    summaryRows.forEach(({ quote, sales }) => {
      const customerName = String(quote.kupacNaziv || "").trim()
      const customerId = String(quote.kupacId || "").trim()
      const isUnassigned = !customerId && !customerName
      const key = getCustomerGroupingKey(quote)
      const displayName = customerName || "Kupac nije naveden"

      let entry = customerMap.get(key)
      if (!entry) {
        entry = {
          key,
          customerName: displayName,
          quoteCount: 0,
          acceptedCount: 0,
          rejectedCount: 0,
          offeredValue: 0,
          acceptedValue: 0,
          isUnassigned,
        }
        customerMap.set(key, entry)
      }

      entry.quoteCount += 1
      entry.offeredValue += sales

      const status = getDashboardStatus(quote)
      if (status === "accepted") {
        entry.acceptedCount += 1
        entry.acceptedValue += sales
      } else if (status === "rejected") {
        entry.rejectedCount += 1
      }
    })

    const customerAnalysis: CustomerAnalysisRow[] = Array.from(customerMap.values())
      .map((customer) => {
        const decidedQuotes = customer.acceptedCount + customer.rejectedCount
        return {
          ...customer,
          successRate: decidedQuotes > 0
            ? (customer.acceptedCount / decidedQuotes) * 100
            : null,
        }
      })
      .sort((a, b) => b.offeredValue - a.offeredValue || a.customerName.localeCompare(b.customerName, "bs"))

    const decidedQuotesOverall = statusCounts.accepted + statusCounts.rejected
    const overallCustomerSuccessRate = decidedQuotesOverall > 0
      ? (statusCounts.accepted / decidedQuotesOverall) * 100
      : null

    return {
      totalQuotes: periodQuotes.length,
      allSales: summaryRows.reduce((sum, row) => sum + row.sales, 0),
      acceptedSales,
      totalCost: costedCosts,
      estimatedProfit,
      marginPercent,
      quotesWithFinance: quotesWithFinance.length,
      statusCounts,
      lowestMarginLines: lowestMarginLines.slice(0, 5),
      customerAnalysis,
      customerCount: customerAnalysis.filter((customer) => !customer.isUnassigned).length,
      overallCustomerSuccessRate,
      measuredPreparationQuoteCount: measuredTimeRows.length,
      totalPreparationTimeMs,
      averagePreparationTimeMs,
      shortestPreparationTimeMs,
      longestPreparationTimeMs,
      preparationTimeByAuthor,
      recentPreparationRows,
    }
  }, [quotes, dashboardPeriod, dashboardDateFrom, dashboardDateTo, dashboardRange.from, dashboardRange.to])

  /*
   * ==========================================
   * RENDER
   * ==========================================
   */
  return (
    <div
      className="quotes-page-polished"
      style={{
        width: "100%",
        maxWidth: "1500px",
        marginLeft: "0",
        marginRight: "auto",
        paddingLeft: "0",
        paddingRight: "20px",
        paddingTop: "14px",
        boxSizing: "border-box",
      }}
    >

      {/* ======================================
          HEADER
         ====================================== */}

      <div
        className="quotes-page-header"
        style={{
          display: "flex",
          justifyContent:
            "space-between",
          alignItems: "center",
          marginBottom: "28px",
        }}
      >

        <div>

          <h1
            className="quotes-page-title"
            style={{
              margin: 0,
              marginBottom: "8px",
            }}
          >
            {view === "analysis" ? "Analiza poslovanja" : "Ponude"}
          </h1>

          <p
            style={{
              margin: 0,
              color: "#64748b",
            }}
          >
            {view === "analysis"
              ? "Profitabilnost, rezultati po kupcima i ponude za praćenje."
              : "Pretraga, pregled i upravljanje spremljenim ponudama."}
          </p>

        </div>

        {/* ======================================
            OSVJEŽI
           ====================================== */}

        <button
          type="button"
          className="quotes-refresh-button"
          onClick={
            loadQuotes
          }
          style={{
            border: "none",
            background:
              "#4f46e5",
            color: "white",
            padding:
              "11px 18px",
            borderRadius:
              "8px",
            cursor:
              "pointer",
            fontWeight: 600,
          }}
        >
          ↻ Osvježi
        </button>

      </div>

      {view === "analysis" && (
        <>
      {/* ======================================
          KONTROLNA TABLA PROFITABILNOSTI
         ====================================== */}
      <section className="profit-dashboard" aria-label="Kontrolna tabla profitabilnosti">
        <div className="profit-dashboard-heading">
          <div>
            <div className="profit-dashboard-eyebrow">FINANSIJSKI PREGLED</div>
            <h2>Kontrolna tabla profitabilnosti</h2>
            <p>Finansijski pokazatelji prema izabranom periodu ponuda.</p>
          </div>
          <div className="profit-dashboard-count">
            <strong>{dashboardData.totalQuotes}</strong>
            <span>ponuda u periodu</span>
          </div>
        </div>

        <div className="profit-dashboard-filters">
          <div className="profit-period-control">
            <label htmlFor="profit-dashboard-period">Period izvještaja</label>
            <select
              id="profit-dashboard-period"
              value={dashboardPeriod}
              onChange={(event) => setDashboardPeriod(event.target.value as DashboardPeriod)}
            >
              <option value="all">Svi datumi</option>
              <option value="thisMonth">Ovaj mjesec</option>
              <option value="lastMonth">Prethodni mjesec</option>
              <option value="thisYear">Ova godina</option>
              <option value="custom">Prilagođeni period</option>
            </select>
          </div>
          {dashboardPeriod === "custom" && (
            <div className="profit-custom-date-range">
              <div className="profit-period-control">
                <label htmlFor="profit-dashboard-from">Od datuma</label>
                <DatePicker
                  id="profit-dashboard-from"
                  selected={dashboardDateFrom}
                  onChange={(date: Date | null) => setDashboardDateFrom(date)}
                  dateFormat="dd.MM.yyyy"
                  locale={bs}
                  placeholderText="Odaberi datum"
                  className="profit-date-input"
                  isClearable
                />
              </div>
              <div className="profit-period-control">
                <label htmlFor="profit-dashboard-to">Do datuma</label>
                <DatePicker
                  id="profit-dashboard-to"
                  selected={dashboardDateTo}
                  onChange={(date: Date | null) => setDashboardDateTo(date)}
                  dateFormat="dd.MM.yyyy"
                  locale={bs}
                  placeholderText="Odaberi datum"
                  className="profit-date-input"
                  minDate={dashboardDateFrom || undefined}
                  isClearable
                />
              </div>
            </div>
          )}
          <div className="profit-period-summary">
            <span>Aktivni period</span>
            <strong>{dashboardRange.label}</strong>
          </div>
          {dashboardPeriod === "custom" && (dashboardRange.invalid || (!dashboardDateFrom && !dashboardDateTo)) && (
            <span className="profit-period-hint">
              {dashboardRange.invalid ? "Krajnji datum mora biti poslije početnog datuma." : "Odaberi početni i/ili krajnji datum za filtriranje."}
            </span>
          )}
        </div>

        {loading ? (
          <div className="profit-dashboard-loading">Učitavanje finansijskih pokazatelja…</div>
        ) : (
          <>
            <div className="profit-dashboard-cards">
              <article className="profit-kpi kpi-blue">
                <span className="profit-kpi-label">Vrijednost ponuda u periodu</span>
                <strong>{formatEuro(dashboardData.allSales)}</strong>
                <small>Svi statusi unutar odabranog perioda</small>
              </article>
              <article className="profit-kpi kpi-slate">
                <span className="profit-kpi-label">Troškovi kalkulacija</span>
                <strong>{formatEuro(dashboardData.totalCost)}</strong>
                <small>{dashboardData.quotesWithFinance} ponuda s potpunim finansijskim podacima</small>
              </article>
              <article className={`profit-kpi ${dashboardData.estimatedProfit >= 0 ? "kpi-green" : "kpi-red"}`}>
                <span className="profit-kpi-label">Očekivana bruto zarada</span>
                <strong>{formatEuro(dashboardData.estimatedProfit)}</strong>
                <small>Prodajna vrijednost minus kalkulisani troškovi</small>
              </article>
              <article className={`profit-kpi ${dashboardData.marginPercent >= 0 ? "kpi-violet" : "kpi-red"}`}>
                <span className="profit-kpi-label">Bruto marža</span>
                <strong>{formatPercent(dashboardData.marginPercent)}</strong>
                <small>Zarada / prodajna vrijednost potpunih kalkulacija</small>
              </article>
              <article className="profit-kpi kpi-teal">
                <span className="profit-kpi-label">Prihvaćene ponude</span>
                <strong>{formatEuro(dashboardData.acceptedSales)}</strong>
                <small>{dashboardData.statusCounts.accepted} prihvaćenih ponuda</small>
              </article>
            </div>

            <div className="profit-status-strip" aria-label="Broj ponuda po statusu">
              <div><span className="status-dot dot-draft" /><span>Nacrt</span><strong>{dashboardData.statusCounts.draft}</strong></div>
              <div><span className="status-dot dot-sent" /><span>Poslane</span><strong>{dashboardData.statusCounts.sent}</strong></div>
              <div><span className="status-dot dot-accepted" /><span>Prihvaćene</span><strong>{dashboardData.statusCounts.accepted}</strong></div>
              <div><span className="status-dot dot-rejected" /><span>Odbijene</span><strong>{dashboardData.statusCounts.rejected}</strong></div>
            </div>

            <div className="profit-lowest-margin">
              <div className="profit-subheading">
                <div>
                  <h3>Pozicije s najnižom bruto maržom</h3>
                  <p>Prikaz do pet pozicija za koje postoje i trošak i prodajna cijena.</p>
                </div>
              </div>
              {dashboardData.lowestMarginLines.length === 0 ? (
                <div className="profit-empty">Još nema dovoljno potpunih kalkulacija za poređenje marži.</div>
              ) : (
                <div className="profit-table-scroll">
                  <table className="profit-table">
                    <thead>
                      <tr>
                        <th>Šifra / naziv</th>
                        <th>Ponuda / kupac</th>
                        <th>Cijena / kom</th>
                        <th>Trošak / kom</th>
                        <th>Zarada / kom</th>
                        <th>Bruto marža</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dashboardData.lowestMarginLines.map((line) => (
                        <tr key={line.key}>
                          <td>
                            <strong>{line.code}</strong>
                            <span className="profit-table-secondary">{line.name}</span>
                          </td>
                          <td>
                            <strong>{line.quoteNumber}</strong>
                            <span className="profit-table-secondary">{line.customer}</span>
                          </td>
                          <td>{formatEuro(line.sellingPerPiece)}</td>
                          <td>{formatEuro(line.costPerPiece)}</td>
                          <td className={line.profitPerPiece < 0 ? "profit-negative" : "profit-positive"}>
                            {formatEuro(line.profitPerPiece)}
                          </td>
                          <td>
                            <span className={`profit-margin-badge ${line.marginPercent < 0 ? "is-negative" : line.marginPercent < 10 ? "is-low" : "is-ok"}`}>
                              {formatPercent(line.marginPercent)}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
            <p className="profit-dashboard-note">
              Napomena: ovo su očekivani rezultati prema sačuvanim ponudama, a ne potvrđena realizovana dobit. Profitabilnost se računa samo za ponude s pozitivnom prodajnom vrijednošću i evidentiranim troškovima. Vrijednost ponuda obuhvata sve statuse unutar odabranog perioda.
            </p>
          </>
        )}
      </section>

      {/* ======================================
          ANALIZA VREMENA IZRADE PONUDA
         ====================================== */}
      <section className="quote-time-dashboard" aria-label="Analiza vremena izrade ponuda">
        <div className="quote-time-heading">
          <div>
            <div className="quote-time-eyebrow">ORGANIZACIJA RADA</div>
            <h2>Vrijeme izrade ponuda</h2>
            <p>Vrijeme se mjeri od otvaranja nove ponude do njenog prvog uspješnog spremanja ili izvoza ponude u PDF/Excel.</p>
          </div>
          <div className="quote-time-period">
            <span>Aktivni period</span>
            <strong>{dashboardRange.label}</strong>
          </div>
        </div>

        {loading ? (
          <div className="quote-time-empty">Učitavanje podataka o vremenu izrade…</div>
        ) : (
          <>
            <div className="profit-dashboard-cards quote-time-kpi-cards">
              <article className="profit-kpi kpi-violet">
                <span className="profit-kpi-label">Prosječno vrijeme po ponudi</span>
                <strong>{dashboardData.measuredPreparationQuoteCount > 0 ? formatPreparationDuration(dashboardData.averagePreparationTimeMs) : "—"}</strong>
                <small>Na osnovu {dashboardData.measuredPreparationQuoteCount} izmjerenih ponuda</small>
              </article>
              <article className="profit-kpi kpi-blue">
                <span className="profit-kpi-label">Izmjerene ponude</span>
                <strong>{dashboardData.measuredPreparationQuoteCount}</strong>
                <small>Broj ponuda s evidentiranim vremenom izrade</small>
              </article>
              <article className="profit-kpi kpi-slate">
                <span className="profit-kpi-label">Ukupno utrošeno vrijeme</span>
                <strong>{dashboardData.measuredPreparationQuoteCount > 0 ? formatPreparationDuration(dashboardData.totalPreparationTimeMs) : "—"}</strong>
                <small>Zbir trajanja svih izmjerenih ponuda u periodu</small>
              </article>
              <article className="profit-kpi kpi-teal">
                <span className="profit-kpi-label">Najbrže izrađena ponuda</span>
                <strong>{dashboardData.measuredPreparationQuoteCount > 0 ? formatPreparationDuration(dashboardData.shortestPreparationTimeMs) : "—"}</strong>
                <small>Najkraće evidentirano vrijeme izrade</small>
              </article>
              <article className="profit-kpi kpi-slate">
                <span className="profit-kpi-label">Najduže izrađena ponuda</span>
                <strong>{dashboardData.measuredPreparationQuoteCount > 0 ? formatPreparationDuration(dashboardData.longestPreparationTimeMs) : "—"}</strong>
                <small>Najduže evidentirano vrijeme izrade</small>
              </article>
            </div>

            <div className="quote-time-tables-grid">
              <div className="quote-time-table-card">
                <div className="quote-time-table-heading">
                  <div>
                    <h3>Prosječno vrijeme po kalkulantu</h3>
                    <p>Poređenje prosječnog vremena izrade po osobi koja je napravila ponudu.</p>
                  </div>
                  <span>{dashboardData.preparationTimeByAuthor.length} korisnika</span>
                </div>
                {dashboardData.preparationTimeByAuthor.length === 0 ? (
                  <div className="quote-time-empty">Još nema izmjerenih ponuda u odabranom periodu.</div>
                ) : (
                  <div className="profit-table-scroll">
                    <table className="profit-table quote-time-table">
                      <thead>
                        <tr>
                          <th>Kalkulant</th>
                          <th>Broj ponuda</th>
                          <th>Ukupno vrijeme</th>
                          <th>Prosjek po ponudi</th>
                        </tr>
                      </thead>
                      <tbody>
                        {dashboardData.preparationTimeByAuthor.map((row) => (
                          <tr key={row.author}>
                            <td><strong>{row.author}</strong></td>
                            <td>{row.count}</td>
                            <td>{formatPreparationDuration(row.totalMs)}</td>
                            <td><strong>{formatPreparationDuration(row.averageMs)}</strong></td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>

              <div className="quote-time-table-card">
                <div className="quote-time-table-heading">
                  <div>
                    <h3>Posljednjih 10 izmjerenih ponuda</h3>
                    <p>Pregled trajanja pojedinačnih ponuda u odabranom periodu.</p>
                  </div>
                  <span>{dashboardData.recentPreparationRows.length} ponuda</span>
                </div>
                {dashboardData.recentPreparationRows.length === 0 ? (
                  <div className="quote-time-empty">Nema ponuda s evidentiranim vremenom izrade.</div>
                ) : (
                  <div className="profit-table-scroll">
                    <table className="profit-table quote-time-table quote-time-recent-table">
                      <thead>
                        <tr>
                          <th>Broj ponude / datum</th>
                          <th>Kupac</th>
                          <th>Kalkulant</th>
                          <th>Vrijeme</th>
                        </tr>
                      </thead>
                      <tbody>
                        {dashboardData.recentPreparationRows.map(({ quote, durationMs }) => (
                          <tr key={quote.id || quote.brojPonude}>
                            <td>
                              <strong>{quote.brojPonude || "Bez broja"}</strong>
                              <span className="quote-time-secondary">{formatDate(quote.vrijemeIzradePonudeKraj || quote.datum)}</span>
                            </td>
                            <td>{quote.kupacNaziv || "—"}</td>
                            <td>{quote.createdBy || "Nepoznat korisnik"}</td>
                            <td><strong>{formatPreparationDuration(durationMs)}</strong></td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </div>

            <p className="quote-time-note">
              Starije ponude koje su spremljene prije uvođenja tajmera nemaju ovaj podatak i nisu uključene u prosjek. Uređivanje već postojeće ponude ne mijenja originalno evidentirano vrijeme.
            </p>
          </>
        )}
      </section>

      {/* ======================================
          ANALIZA POSLOVANJA PO KUPCIMA
         ====================================== */}
      <section className="customer-analysis-dashboard" aria-label="Analiza poslovanja po kupcima">
        <div className="customer-analysis-heading">
          <div>
            <div className="customer-analysis-eyebrow">KUPCI I PRODAJA</div>
            <h2>Analiza poslovanja po kupcima</h2>
            <p>Uporedi vrijednost ponuda i uspješnost po kupcu. Ova analiza koristi isti period kao kontrolna tabla profitabilnosti.</p>
          </div>
          <div className="customer-analysis-period">
            <span>Aktivni period</span>
            <strong>{dashboardRange.label}</strong>
          </div>
        </div>

        {loading ? (
          <div className="profit-dashboard-loading">Učitavanje analize kupaca…</div>
        ) : (
          <>
            <div className="profit-dashboard-cards customer-analysis-cards">
              <article className="profit-kpi kpi-violet">
                <span className="profit-kpi-label">Broj kupaca</span>
                <strong>{dashboardData.customerCount}</strong>
                <small>Jedinstveni kupci u odabranom periodu</small>
              </article>
              <article className="profit-kpi kpi-blue">
                <span className="profit-kpi-label">Broj ponuda</span>
                <strong>{dashboardData.totalQuotes}</strong>
                <small>Svi statusi u odabranom periodu</small>
              </article>
              <article className="profit-kpi kpi-slate">
                <span className="profit-kpi-label">Ukupna ponuđena vrijednost</span>
                <strong>{formatEuro(dashboardData.allSales)}</strong>
                <small>Vrijednost ponuda svih statusa</small>
              </article>
              <article className="profit-kpi kpi-teal">
                <span className="profit-kpi-label">Vrijednost prihvaćenih</span>
                <strong>{formatEuro(dashboardData.acceptedSales)}</strong>
                <small>Samo ponude sa statusom "Prihvaćena"</small>
              </article>
              <article className="profit-kpi kpi-green">
                <span className="profit-kpi-label">Stopa uspješnosti</span>
                <strong>{dashboardData.overallCustomerSuccessRate === null ? "—" : formatPercent(dashboardData.overallCustomerSuccessRate)}</strong>
                <small>Prihvaćene / (prihvaćene + odbijene)</small>
              </article>
            </div>

            <div className="customer-analysis-table-card">
              <div className="profit-subheading">
                <div>
                  <h3>Rezultati po kupcu</h3>
                  <p>Sortirano od najveće do najmanje ukupne ponuđene vrijednosti.</p>
                </div>
                <span className="customer-analysis-result-count">{dashboardData.customerAnalysis.length} grupa</span>
              </div>

              {dashboardData.customerAnalysis.length === 0 ? (
                <div className="profit-empty">Nema ponuda za izabrani period.</div>
              ) : (
                <div className="profit-table-scroll customer-analysis-table-scroll">
                  <table className="profit-table customer-analysis-table">
                    <thead>
                      <tr>
                        <th>Kupac</th>
                        <th>Broj ponuda</th>
                        <th>Ukupna vrijednost</th>
                        <th>Prihvaćene</th>
                        <th>Vrijednost prihvaćenih</th>
                        <th>Odbijene</th>
                        <th>Stopa uspješnosti</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dashboardData.customerAnalysis.map((customer) => (
                        <tr
                          key={customer.key}
                          className={selectedCustomerHistoryKey === customer.key ? "customer-analysis-row-selected" : ""}
                        >
                          <td>
                            <button
                              type="button"
                              className={`customer-history-select ${selectedCustomerHistoryKey === customer.key ? "is-selected" : ""}`}
                              onClick={() => setSelectedCustomerHistoryKey((current) => current === customer.key ? null : customer.key)}
                              aria-pressed={selectedCustomerHistoryKey === customer.key}
                              aria-label={`${selectedCustomerHistoryKey === customer.key ? "Sakrij" : "Prikaži"} historiju kupca ${customer.customerName}`}
                              title="Prikaži kompletnu historiju saradnje"
                            >
                              <span>{customer.customerName}</span>
                              <span className="customer-history-select-icon" aria-hidden="true">
                                {selectedCustomerHistoryKey === customer.key ? "▾" : "↗"}
                              </span>
                            </button>
                            {customer.isUnassigned && <span className="profit-table-secondary">Provjeri podatke kupca</span>}
                          </td>
                          <td>{customer.quoteCount}</td>
                          <td>{formatEuro(customer.offeredValue)}</td>
                          <td>{customer.acceptedCount}</td>
                          <td>{formatEuro(customer.acceptedValue)}</td>
                          <td>{customer.rejectedCount}</td>
                          <td>
                            {customer.successRate === null ? (
                              <span className="customer-analysis-no-rate">Nema odluka</span>
                            ) : (
                              <span className={`profit-margin-badge ${customer.successRate < 25 ? "is-low" : "is-ok"}`}>
                                {formatPercent(customer.successRate)}
                              </span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {selectedCustomerHistory && (
              <section className="customer-history-panel" aria-label={`Historija kupca ${selectedCustomerHistory.customerName}`}>
                <div className="customer-history-heading">
                  <div>
                    <div className="customer-history-eyebrow">HISTORIJA SARADNJE</div>
                    <h3>{selectedCustomerHistory.customerName}</h3>
                    <p>Pregled svih ponuda ovog kupca, neovisno o trenutno odabranom periodu kontrolne table.</p>
                  </div>
                  <button
                    type="button"
                    className="customer-history-close-button"
                    onClick={() => setSelectedCustomerHistoryKey(null)}
                  >
                    Zatvori historiju ✕
                  </button>
                </div>

                {selectedCustomerHistory.isUnassigned && (
                  <div className="customer-history-warning">
                    Ova grupa sadrži ponude bez dodijeljenog kupca. Preporučujemo da provjeriš podatke i povežeš ponude s odgovarajućim kupcima.
                  </div>
                )}

                <div className="customer-history-metrics">
                  <article>
                    <span>Ukupno ponuda</span>
                    <strong>{selectedCustomerHistory.quoteCount}</strong>
                  </article>
                  <article>
                    <span>Ukupna ponuđena vrijednost</span>
                    <strong>{formatEuro(selectedCustomerHistory.offeredValue)}</strong>
                  </article>
                  <article>
                    <span>Prihvaćene ponude</span>
                    <strong>{selectedCustomerHistory.acceptedCount}</strong>
                  </article>
                  <article className="is-accepted-value">
                    <span>Vrijednost prihvaćenih</span>
                    <strong>{formatEuro(selectedCustomerHistory.acceptedValue)}</strong>
                  </article>
                  <article>
                    <span>Stopa uspješnosti</span>
                    <strong>{selectedCustomerHistory.successRate === null ? "—" : formatPercent(selectedCustomerHistory.successRate)}</strong>
                  </article>
                </div>

                <div className="customer-history-list-heading">
                  <div>
                    <h4>Historija ponuda</h4>
                    <p>{selectedCustomerHistory.rejectedCount} odbijenih ponuda · najnovije prvo</p>
                  </div>
                  <span>{selectedCustomerHistory.quotes.length} zapisa</span>
                </div>

                {selectedCustomerHistory.quotes.length === 0 ? (
                  <div className="profit-empty">Za ovog kupca nema evidentiranih ponuda.</div>
                ) : (
                  <div className="customer-history-table-scroll">
                    <table className="customer-history-table">
                      <thead>
                        <tr>
                          <th>Datum</th>
                          <th>Broj ponude</th>
                          <th>Status</th>
                          <th>Broj pozicija</th>
                          <th>Prodajna vrijednost</th>
                          <th>Autor</th>
                          <th>Akcija</th>
                        </tr>
                      </thead>
                      <tbody>
                        {selectedCustomerHistory.quotes.map((quote) => (
                          <tr key={quote.id || quote.brojPonude}>
                            <td>{formatDate(quote.datum)}</td>
                            <td><strong>{quote.brojPonude || "—"}</strong></td>
                            <td><span className={getStatusClass(getDashboardStatus(quote))}>{getStatusName(getDashboardStatus(quote))}</span></td>
                            <td>{Array.isArray(quote.positions) ? quote.positions.length : 0}</td>
                            <td><strong>{formatEuro(getQuoteFinance(quote).sales)}</strong></td>
                            <td>{quote.createdBy || "—"}</td>
                            <td>
                              <button
                                type="button"
                                className="customer-history-open-button"
                                onClick={() => setSelectedQuote(quote)}
                              >
                                Otvori ponudu
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </section>
            )}

            <p className="profit-dashboard-note">
              Stopa uspješnosti računa se kao broj prihvaćenih podijeljen zbirom prihvaćenih i odbijenih ponuda. Nacrti i poslane ponude nisu uključeni jer odluka kupca još nije poznata. Ovo je analiza ponuda, ne potvrda realizovanog prometa niti naplaćenih prihoda. Grupe bez naziva kupca prikazane su zasebno kako se njihove ponude ne bi izgubile.
            </p>
          </>
        )}
      </section>
        </>
      )}

      {/* ======================================
          PONUDE ZA PRAĆENJE
         ====================================== */}
      {view === "offers" && (
      <section className="quote-followup-dashboard" aria-label="Ponude za praćenje kupaca">
        <div className="quote-followup-heading">
          <div>
            <div className="quote-followup-eyebrow">PRODAJNE AKTIVNOSTI</div>
            <h2>Ponude za praćenje</h2>
            <p>Ovdje su samo ponude koje si označio za praćenje. U tabeli ponuda izaberi "Dodaj u praćenje"; za nacrt će aplikacija prvo tražiti potvrdu da je ponuda poslana.</p>
          </div>
          <div className="quote-followup-count">
            <strong>{followUpQuotes.length}</strong>
            <span>otvorenih aktivnosti</span>
          </div>
        </div>

        {loading ? (
          <div className="followup-empty">Učitavanje ponuda za praćenje…</div>
        ) : followUpQuotes.length === 0 ? (
          <div className="followup-empty">Nema označenih ponuda. U tabeli ispod pronađi ponudu i klikni "Dodaj u praćenje" ili "Poslano + prati".</div>
        ) : (
          <div className="followup-table-scroll">
            <table className="followup-table">
              <thead>
                <tr>
                  <th>Ponuda / kupac</th>
                  <th>Vrijednost</th>
                  <th>Datum slanja</th>
                  <th>Posljednji kontakt</th>
                  <th>Sljedeći kontakt</th>
                  <th>Aktivnost</th>
                  <th>Akcija</th>
                </tr>
              </thead>
              <tbody>
                {followUpQuotes.map((quote) => {
                  const due = getFollowUpDue(quote.datumSljedecegKontakta)
                  const latestContact = [...(quote.historijaKontakata || [])]
                    .sort((a, b) => (getDateOnly(b.datum)?.getTime() || 0) - (getDateOnly(a.datum)?.getTime() || 0))[0]
                  return (
                    <tr key={quote.id || quote.brojPonude}>
                      <td>
                        <strong>{quote.brojPonude || "—"}</strong>
                        <span className="followup-table-secondary">{quote.kupacNaziv || "Kupac nije naveden"}</span>
                      </td>
                      <td>{formatMoney(quote.totalSellingPrice)}</td>
                      <td>{quote.datumSlanja ? formatDate(quote.datumSlanja) : "Nije evidentiran"}</td>
                      <td>
                        {latestContact ? (
                          <>
                            <span>{formatDate(latestContact.datum)}</span>
                            <span className="followup-table-secondary">{getContactChannelName(latestContact.kanal)} · {getContactOutcomeName(latestContact.ishod)}</span>
                          </>
                        ) : "Još nije kontaktiran"}
                      </td>
                      <td>
                        <span>{formatDate(quote.datumSljedecegKontakta)}</span>
                        <span className={`followup-due-badge ${due.className}`}>{due.label}</span>
                      </td>
                      <td>{getFollowUpStatusName(quote.statusPracenja)}</td>
                      <td>
                        <button
                          type="button"
                          onClick={() => setSelectedQuote(quote)}
                          className="followup-open-button"
                        >
                          Otvori praćenje
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>
      )}

      {view === "offers" && (
        <>
      {/* ======================================
          FILTERI
         ====================================== */}

      <div
        className="quotes-search-filters"
        style={{
          background: "white",
          border:
            "1px solid #e2e8f0",
          borderRadius: "12px",
          padding: "20px",
          marginBottom: "24px",
        }}
      >

        {/* ======================================
            TEKSTUALNA PRETRAGA
           ====================================== */}

        <label
          className="quotes-search-label"
          style={{
            display: "block",
            marginBottom: "8px",
            fontWeight: 600,
          }}
        >
          Pretraga
        </label>

        <input
          className="quotes-search-input"
          type="text"
          value={search}
          onChange={(event) =>
            setSearch(
              event.target.value,
            )
          }
          placeholder="Broj ponude, broj upita kupca, kupac, šifra ili naziv pozicije, korisnik..."
          style={{
            width: "100%",
            boxSizing:
              "border-box",
            padding:
              "12px 14px",
            border:
              "1px solid #cbd5e1",
            borderRadius:
              "8px",
            fontSize:
              "15px",
            outline:
              "none",
          }}
        />

        {/* ======================================
            STATUS + SORTIRANJE
           ====================================== */}

        <div
          className="quotes-search-filter-row"
          style={{
            marginTop: "16px",
            display: "flex",
            alignItems: "center",
            gap: "28px",
            flexWrap: "wrap",
          }}
        >

          {/* STATUS */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "10px",
            }}
          >

          <label
            htmlFor="statusFilter"
            style={{
              fontWeight: 600,
              whiteSpace:
                "nowrap",
            }}
          >
            Status:
          </label>

          <select
            id="statusFilter"
            value={
              statusFilter
            }
            onChange={(event) =>
              setStatusFilter(
                event.target
                  .value as StatusFilter,
              )
            }
            style={{
              minWidth:
                "180px",
              padding:
                "10px 12px",
              border:
                "1px solid #cbd5e1",
              borderRadius:
                "8px",
              background:
                "white",
              fontSize:
                "14px",
              cursor:
                "pointer",
              outline:
                "none",
            }}
          >

            <option value="all">
              Svi statusi
            </option>

            <option value="draft">
              Nacrt
            </option>

            <option value="sent">
              Poslana
            </option>

            <option value="accepted">
              Prihvaćena
            </option>

            <option value="rejected">
              Odbijena
            </option>

          </select>

          </div>

          {/* SORTIRANJE */}
          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "10px",
            }}
          >

          <label
            htmlFor="sortFilter"
            style={{
              fontWeight: 600,
              whiteSpace: "nowrap",
            }}
          >
            Sortiranje:
          </label>

          <select
            id="sortFilter"
            value={sortOption}
            onChange={(event) =>
              setSortOption(
                event.target.value as SortOption,
              )
            }
            style={{
              minWidth: "220px",
              padding: "10px 12px",
              border: "1px solid #cbd5e1",
              borderRadius: "8px",
              background: "white",
              fontSize: "14px",
              cursor: "pointer",
              outline: "none",
            }}
          >

            <option value="newest">
              Najnovije prvo
            </option>

            <option value="oldest">
              Najstarije prvo
            </option>

            <option value="quoteAsc">
              Broj ponude A–Z
            </option>

            <option value="quoteDesc">
              Broj ponude Z–A
            </option>

            <option value="customerAsc">
              Kupac A–Z
            </option>

            <option value="customerDesc">
              Kupac Z–A
            </option>

            <option value="valueDesc">
              Najveća vrijednost
            </option>

            <option value="valueAsc">
              Najmanja vrijednost
            </option>

          </select>

          </div>
        </div>

        {/* ======================================
            FILTER DATUMA
           ====================================== */}

        <div
          className="quotes-search-date-row"
          style={{
            marginTop: "16px",
            display: "flex",
            alignItems: "center",
            gap: "24px",
            flexWrap: "wrap",
          }}
        >

          {/* ======================================
              DATUM OD
             ====================================== */}

          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
            }}
          >

            <label
              htmlFor="dateFrom"
              style={{
                fontWeight: 600,
              }}
            >
              Datum od:
            </label>

            <DatePicker
              id="dateFrom"

              /*
               * Trenutno odabrani datum.
               */
              selected={
                dateFrom
              }

              /*
               * Kada korisnik odabere
               * datum, spremamo Date objekat.
               */
              onChange={(
                date: Date | null,
              ) => {
                setDateFrom(
                  date,
                )
              }}

              /*
               * Bosanska lokalizacija.
               *
               * Sedmica počinje
               * ponedjeljkom.
               */
              locale={bs}

              /*
               * Prikaz datuma.
               */
              dateFormat=
                "dd.MM.yyyy"

              /*
               * Tekst kada datum
               * nije odabran.
               */
              placeholderText=
                "Od datuma"

              /*
               * Uklanjamo strelicu
               * iznad kalendara.
               */
              showPopperArrow={
                false
              }

              /*
               * Omogućavamo brisanje
               * odabranog datuma.
               */
              isClearable

              /*
               * Korisniku prikazujemo
               * današnji mjesec kada
               * nema odabranog datuma.
               */
              todayButton=
                "Danas"

              /*
               * Na kalendaru želimo
               * prikaz sedmice.
               */
              showWeekNumbers={
                false
              }

              /*
               * Stil našeg polja.
               */
              className=
                "quote-date-picker"

              wrapperClassName=
                "quote-date-picker-wrapper"
            />

          </div>

          {/* ======================================
              DATUM DO
             ====================================== */}

          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
            }}
          >

            <label
              htmlFor="dateTo"
              style={{
                fontWeight: 600,
              }}
            >
              Datum do:
            </label>

            <DatePicker
              id="dateTo"

              /*
               * Trenutno odabrani datum.
               */
              selected={
                dateTo
              }

              /*
               * Spremamo odabrani datum.
               */
              onChange={(
                date: Date | null,
              ) => {
                setDateTo(
                  date,
                )
              }}

              /*
               * Bosanska lokalizacija.
               *
               * Ponedjeljak je prvi
               * dan sedmice.
               */
              locale={bs}

              /*
               * Prikaz:
               *
               * DD.MM.YYYY
               */
              dateFormat=
                "dd.MM.yyyy"

              /*
               * Placeholder.
               */
              placeholderText=
                "Do datuma"

              /*
               * Bez strelice.
               */
              showPopperArrow={
                false
              }

              /*
               * Omogućavamo brisanje.
               */
              isClearable

              /*
               * Dugme za današnji datum.
               */
              todayButton=
                "Danas"

              /*
               * ======================================
               * MINIMALNI DATUM
               * ======================================
               *
               * Ako je odabran "Datum od",
               * ne možemo izabrati datum
               * prije njega.
               */
              minDate={
                dateFrom ||
                undefined
              }

              /*
               * Stil.
               */
              className=
                "quote-date-picker"

              wrapperClassName=
                "quote-date-picker-wrapper"
            />

          </div>

        </div>

        {/* ======================================
            BROJ PRONAĐENIH PONUDA
           ====================================== */}

        <div
          className="quotes-search-result-count"
          style={{
            marginTop: "12px",
            color: "#64748b",
            fontSize: "14px",
          }}
        >
          Pronađeno:
          {" "}

          <strong>
            {
              filteredQuotes.length
            }
          </strong>

          {" "}

          ponuda
        </div>

      </div>

      {/* ======================================
          TABELA PONUDA
         ====================================== */}

      <div
        className="quotes-results-card"
        style={{
          background:
            "white",
          border:
            "1px solid #e2e8f0",
          borderRadius:
            "12px",
          overflow:
            "hidden",
        }}
      >

        {/* ======================================
            UČITAVANJE
           ====================================== */}

        {loading ? (

          <div
            style={{
              padding:
                "40px",
              textAlign:
                "center",
              color:
                "#64748b",
            }}
          >
            Učitavanje ponuda...
          </div>

        ) : filteredQuotes.length ===
          0 ? (

          /* ======================================
             NEMA REZULTATA
             ====================================== */

          <div
            style={{
              padding:
                "50px",
              textAlign:
                "center",
              color:
                "#64748b",
            }}
          >
            Nema pronađenih
            ponuda.
          </div>

        ) : (

          /* ======================================
             TABELA
             ====================================== */

          <div
            className="quotes-results-table-scroll"
            style={{
              overflowX:
                "auto",
            }}
          >

            <table
              className="quotes-results-table"
              style={{
                width:
                  "100%",
                minWidth:
                  "1465px",
                textAlign:
                  "left",
                borderCollapse:
                  "collapse",
                tableLayout:
                  "fixed",
              }}
            >

              {/* ======================================
                  ZAGLAVLJE
                 ====================================== */}

              <thead>

                <tr
                  style={{
                    background:
                      "#f8fafc",
                  }}
                >

                  <th
                    style={{
                      width:
                        "235px",
                    }}
                  >
                    Broj ponude
                  </th>

                  <th
                    style={{
                      width:
                        "205px",
                    }}
                  >
                    Kupac
                  </th>

                  <th
                    style={{
                      width:
                        "120px",
                    }}
                  >
                    Datum
                  </th>

                  <th
                    style={{
                      width:
                        "85px",
                    }}
                  >
                    Pozicije
                  </th>

                  <th
                    style={{
                      width:
                        "180px",
                    }}
                  >
                    Prodajna vrijednost
                  </th>

                  <th
                    style={{
                      width:
                        "120px",
                    }}
                  >
                    Status
                  </th>

                  <th
                    style={{
                      width:
                        "170px",
                    }}
                  >
                    Kreirao
                  </th>

                  <th
                    style={{
                      width: "350px",
                      minWidth: "350px",
                    }}
                  >
                    Akcije
                  </th>

                </tr>

              </thead>

              {/* ======================================
                  REDOVI
                 ====================================== */}

              <tbody>

                {paginatedQuotes.map(
                  (quote) => (

                    <tr
                      key={
                        quote.id
                      }
                      className="quotes-result-row"
                      style={{
                        borderTop:
                          "1px solid #e2e8f0",
                      }}
                    >

                      {/* BROJ PONUDE */}

                      <td
                        className="quote-number-cell"
                        style={{
                          ...tdStyle,
                          width: "235px",
                          minWidth: "235px",
                          whiteSpace: "normal",
                          overflowWrap: "anywhere",
                          verticalAlign: "middle",
                        }}
                      >
                        <strong style={{ display: "block", maxWidth: "100%", whiteSpace: "normal", overflowWrap: "anywhere", lineHeight: 1.35 }}>
                          {
                            quote.brojPonude
                          }
                        </strong>
                      </td>

                      {/* KUPAC */}

                      <td
                        className="quote-customer-cell"
                        style={{
                          ...tdStyle,
                          width: "205px",
                          minWidth: "205px",
                          whiteSpace: "normal",
                          overflowWrap: "anywhere",
                          verticalAlign: "middle",
                        }}
                      >
                        <strong style={{ display: "block", maxWidth: "100%", whiteSpace: "normal", overflowWrap: "anywhere", lineHeight: 1.35 }}>
                          {
                            quote.kupacNaziv ||
                            "-"
                          }
                        </strong>
                      </td>

                      {/* DATUM */}

                      <td
                        style={
                          tdStyle
                        }
                      >
                        {
                          formatDate(
                            quote.datum,
                          )
                        }
                      </td>

                      {/* POZICIJE */}

                      <td
                        style={
                          tdStyle
                        }
                      >
                        {
                          quote
                            .positions
                            ?.length ||
                          0
                        }
                      </td>

                      {/* PRODAJNA VRIJEDNOST */}

                      <td
                        style={{
                          ...tdStyle,
                          fontWeight:
                            700,
                        }}
                      >
                        {
                          formatMoney(
                            quote.totalSellingPrice,
                          )
                        }
                      </td>

                      {/* STATUS */}

                      <td
                        style={
                          tdStyle
                        }
                      >

                        <span
                          className={
                            getStatusClass(
                              quote.status,
                            )
                          }
                        >
                          {
                            getStatusName(
                              quote.status,
                            )
                          }
                        </span>

                      </td>

                      {/* KREIRAO */}

                      <td
                        style={
                          tdStyle
                        }
                      >
                        {
                          quote.createdBy ||
                          "-"
                        }
                      </td>

                      {/* AKCIJA */}

                      <td className="quote-actions-cell" style={{ ...tdStyle, whiteSpace: "normal", minWidth: "430px" }}>
                        <div className="quotes-row-actions" style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                          <button
                            type="button"
                            className="quotes-row-action is-open"
                            onClick={() => setSelectedQuote(quote)}
                            style={buttonStyle}
                          >
                            👁 Otvori
                          </button>
                          {canUpdateQuote && (quote.status === "draft" || quote.status === "sent") && (
                            <button
                              type="button"
                              className="quotes-row-action is-followup"
                              onClick={() => void handleToggleQuoteFollowUp(quote)}
                              disabled={savingFollowUpQuoteId === quote.id}
                              title={quote.status === "draft"
                                ? "Označi ponudu kao poslanu i dodaj je na listu praćenja"
                                : isQuoteActiveForFollowUp(quote)
                                  ? "Ukloni ovu ponudu s liste praćenja"
                                  : "Dodaj ovu poslanu ponudu na listu praćenja"}
                              style={{
                                ...buttonStyle,
                                borderColor: isQuoteActiveForFollowUp(quote) ? "#0f766e" : "#a5b4fc",
                                background: isQuoteActiveForFollowUp(quote) ? "#ecfdf5" : "#eef2ff",
                                color: isQuoteActiveForFollowUp(quote) ? "#047857" : "#4338ca",
                                cursor: savingFollowUpQuoteId === quote.id ? "wait" : "pointer",
                                opacity: savingFollowUpQuoteId === quote.id ? 0.65 : 1,
                              }}
                            >
                              {savingFollowUpQuoteId === quote.id
                                ? "Spremam…"
                                : quote.status === "draft"
                                  ? "📨 Poslano + prati"
                                  : isQuoteActiveForFollowUp(quote)
                                    ? "✓ U praćenju · Ukloni"
                                    : "＋ Dodaj u praćenje"}
                            </button>
                          )}
                          {canCreateQuote && <button
                            type="button"
                            className="quotes-row-action is-copy"
                            onClick={() => handleCopyQuote(quote)}
                            style={{ ...buttonStyle, borderColor: "#0f766e", background: "#f0fdfa", color: "#0f766e" }}
                            title="Kreiraj novu ponudu na osnovu ove ponude"
                          >
                            📄 Kopiraj
                          </button>}
                          {canDeleteQuote && <button
                            type="button"
                            className="quotes-row-action is-delete"
                            onClick={() => void handleDeleteQuote(quote)}
                            disabled={deletingQuoteId === quote.id}
                            style={{
                              ...buttonStyle,
                              borderColor: "#dc2626",
                              background: deletingQuoteId === quote.id ? "#f1f5f9" : "#fef2f2",
                              color: "#b91c1c",
                              cursor: deletingQuoteId === quote.id ? "wait" : "pointer",
                              opacity: deletingQuoteId === quote.id ? 0.65 : 1,
                            }}
                            title="Trajno obriši ovu ponudu"
                          >
                            {deletingQuoteId === quote.id ? "Brišem..." : "🗑 Obriši"}
                          </button>}
                        </div>
                      </td>

                    </tr>

                  ),
                )}

              </tbody>

            </table>

          </div>
        )}

        {!loading && filteredQuotes.length > 0 && (
          <div
            className="quotes-pagination"
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "space-between",
              gap: "16px",
              flexWrap: "wrap",
              padding: "16px 20px",
              borderTop: "1px solid #e2e8f0",
              background: "#fff",
            }}
          >
            <span style={{ color: "#64748b", fontSize: "14px" }}>
              Prikazano <strong style={{ color: "#334155" }}>{firstVisibleQuote}–{lastVisibleQuote}</strong> od{" "}
              <strong style={{ color: "#334155" }}>{filteredQuotes.length}</strong> ponuda
            </span>

            <label style={{ display: "inline-flex", alignItems: "center", gap: "9px", color: "#334155", fontSize: "14px" }}>
              Prikaži:
              <select
                value={pageSize}
                onChange={(event) => setPageSize(Number(event.target.value))}
                style={{
                  padding: "7px 10px",
                  border: "1px solid #cbd5e1",
                  borderRadius: "7px",
                  background: "#fff",
                  color: "#334155",
                  fontSize: "14px",
                  cursor: "pointer",
                }}
                aria-label="Broj ponuda po stranici"
              >
                {[10, 25, 50, 100].map((size) => (
                  <option key={size} value={size}>{size}</option>
                ))}
              </select>
            </label>

            <div style={{ display: "flex", alignItems: "center", gap: "6px", flexWrap: "wrap" }}>
              <button
                type="button"
                onClick={() => setCurrentPage(1)}
                disabled={safeCurrentPage === 1}
                style={paginationButtonStyle(safeCurrentPage === 1)}
                aria-label="Prva stranica"
              >«</button>
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={safeCurrentPage === 1}
                style={paginationButtonStyle(safeCurrentPage === 1)}
              >Prethodna</button>

              {visiblePages.map((page, index) => (
                <span key={page} style={{ display: "inline-flex", alignItems: "center", gap: "6px" }}>
                  {index > 0 && page - visiblePages[index - 1] > 1 && (
                    <span style={{ color: "#94a3b8", padding: "0 3px" }}>…</span>
                  )}
                  <button
                    type="button"
                    onClick={() => setCurrentPage(page)}
                    aria-current={safeCurrentPage === page ? "page" : undefined}
                    style={paginationButtonStyle(false, safeCurrentPage === page)}
                  >{page}</button>
                </span>
              ))}

              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
                disabled={safeCurrentPage === totalPages}
                style={paginationButtonStyle(safeCurrentPage === totalPages)}
              >Sljedeća</button>
              <button
                type="button"
                onClick={() => setCurrentPage(totalPages)}
                disabled={safeCurrentPage === totalPages}
                style={paginationButtonStyle(safeCurrentPage === totalPages)}
                aria-label="Posljednja stranica"
              >»</button>
            </div>
          </div>
        )}

      </div>

        </>
      )}

      {/* ======================================
          DETALJI PONUDE
         ====================================== */}

      {selectedQuote && (

        <div
          style={{
            marginTop:
              "24px",
            background:
              "white",
            border:
              "1px solid #e2e8f0",
            borderRadius:
              "12px",
            padding:
              "24px",
          }}
        >

          {/* ======================================
              HEADER DETALJA
             ====================================== */}

          <div
            style={{
              display:
                "flex",
              justifyContent:
                "space-between",
              alignItems:
                "center",
              marginBottom:
                "20px",
            }}
          >

            <div>

              <h2
                style={{
                  margin: 0,
                }}
              >
                Ponuda{" "}
                {
                  selectedQuote.brojPonude
                }
              </h2>

              {/* DATUM */}

              <div
                style={{
                  marginTop:
                    "5px",
                  color:
                    "#64748b",
                }}
              >
                Datum:{" "}
                {
                  formatDate(
                    selectedQuote.datum,
                  )
                }
              </div>

              {/* KUPAC */}

              <div
                style={{
                  marginTop:
                    "5px",
                  color:
                    "#64748b",
                }}
              >
                Kupac:{" "}

                <strong>
                  {
                    selectedQuote.kupacNaziv ||
                    "-"
                  }
                </strong>
              </div>

            </div>

            {/* ZATVORI */}

            <div
              style={{
                display: "flex",
                gap: "10px",
                alignItems: "center",
                justifyContent: "flex-end",
                flexWrap: "wrap",
              }}
            >
              <button
                type="button"
                onClick={() => exportQuoteToPdf(selectedQuote)}
                style={buttonStyle}
                title="Otvori ponudu za štampu ili čuvanje kao PDF"
              >
                📄 Izvezi PDF
              </button>

              <button
                type="button"
                onClick={openTgExportModal}
                disabled={tgExporting}
                style={{ ...buttonStyle, borderColor: "#bfdbfe", background: tgExporting ? "#f1f5f9" : "#eff6ff", color: "#1d4ed8", cursor: tgExporting ? "wait" : "pointer", opacity: tgExporting ? 0.65 : 1 }}
                title="Generiši Trendy Germany Angebot sa dodatnom maržom"
              >
                {tgExporting ? "⏳ Priprema PDF TG..." : "📄 Izvezi PDF TG"}
              </button>

              <button
                type="button"
                onClick={() => exportQuotePositionsToExcel(selectedQuote)}
                style={buttonStyle}
                title="Preuzmi Excel-kompatibilnu tabelu pozicija bez zaglavlja ponude"
              >
                📊 Izvezi Excel
              </button>

              {canCreateQuote && <button
                type="button"
                onClick={() => handleCopyQuote(selectedQuote)}
                style={{ ...buttonStyle, borderColor: "#0f766e", background: "#f0fdfa", color: "#0f766e" }}
                title="Kreiraj novu ponudu na osnovu ove ponude"
              >
                📄 Kopiraj ponudu
              </button>}

              {canDeleteQuote && <button
                type="button"
                onClick={() => void handleDeleteQuote(selectedQuote)}
                disabled={deletingQuoteId === selectedQuote.id}
                style={{
                  ...buttonStyle,
                  borderColor: "#dc2626",
                  background: deletingQuoteId === selectedQuote.id ? "#f1f5f9" : "#fef2f2",
                  color: "#b91c1c",
                  cursor: deletingQuoteId === selectedQuote.id ? "wait" : "pointer",
                  opacity: deletingQuoteId === selectedQuote.id ? 0.65 : 1,
                }}
              >
                {deletingQuoteId === selectedQuote.id ? "Brišem..." : "🗑 Obriši ponudu"}
              </button>}

              {canUpdateQuote && <button
                type="button"
                onClick={() => {
                  const quoteId = selectedQuote.id

                  if (!quoteId) {
                    window.alert(
                      "Nije moguće urediti ponudu jer nedostaje njen ID.",
                    )
                    return
                  }

                  // NewQuote.tsx koristi parametar ?edit=<ID>
                  // kako bi učitao postojeću ponudu umjesto nove.
                  const editUrl = new URL(
                    "/new-quote",
                    window.location.origin,
                  )
                  editUrl.searchParams.set("edit", quoteId)
                  window.location.assign(
                    `${editUrl.pathname}${editUrl.search}`,
                  )
                }}
                style={buttonStyle}
              >
                ✏️ Uredi ponudu
              </button>}

              <button
                type="button"
                onClick={() => {
                  setSelectedQuote(null)
                }}
                style={buttonStyle}
              >
                Zatvori
              </button>
            </div>

          </div>

          {selectedQuote.status !== "draft" && (
            <section className="quote-followup-form" aria-label="Praćenje odabrane ponude">
              <div className="quote-followup-form-heading">
                <div>
                  <h3>Praćenje kupca</h3>
                  <p>Prati rokove, evidentiraj kontakte i zabilježi konačan ishod ponude. Historija kontakata ostaje sačuvana i nakon završetka aktivnosti.</p>
                </div>
                <div className="quote-followup-header-pills">
                  <span className="quote-followup-status-pill">
                    {getFollowUpStatusName(followUpForm.statusPracenja)}
                  </span>
                  <span className={`quote-followup-status-pill quote-followup-outcome-pill outcome-${followUpForm.ishodPonude}`}>
                    Ishod: {getFollowUpQuoteOutcomeName(followUpForm.ishodPonude)}
                  </span>
                </div>
              </div>

              <div className="quote-followup-fields">
                <label>
                  Datum slanja
                  <input
                    type="date"
                    value={followUpForm.datumSlanja}
                    onChange={(event) => setFollowUpForm((current) => ({ ...current, datumSlanja: event.target.value }))}
                  />
                </label>
                <label>
                  Sljedeći kontakt
                  <input
                    type="date"
                    value={followUpForm.datumSljedecegKontakta}
                    onChange={(event) => setFollowUpForm((current) => ({ ...current, datumSljedecegKontakta: event.target.value }))}
                  />
                </label>
                <label>
                  Status aktivnosti
                  <select
                    value={followUpForm.statusPracenja}
                    onChange={(event) => setFollowUpForm((current) => ({ ...current, statusPracenja: event.target.value as FollowUpStatus }))}
                  >
                    <option value="waiting">Čekamo odgovor</option>
                    <option value="contact">Kontaktirati kupca</option>
                    <option value="completed">Završeno</option>
                  </select>
                </label>
                <label>
                  Ishod ponude
                  <select
                    value={followUpForm.ishodPonude}
                    onChange={(event) => setFollowUpForm((current) => ({ ...current, ishodPonude: event.target.value as FollowUpQuoteOutcome }))}
                  >
                    <option value="sent">Poslana / čeka odgovor</option>
                    <option value="accepted">Prihvaćena</option>
                    <option value="rejected">Odbijena</option>
                  </select>
                </label>
                <label className="quote-followup-notes-field">
                  Napomena o komunikaciji
                  <textarea
                    rows={3}
                    value={followUpForm.napomenaPracenja}
                    onChange={(event) => setFollowUpForm((current) => ({ ...current, napomenaPracenja: event.target.value }))}
                    placeholder="Npr. kupac će provjeriti ponudu i javiti se do petka..."
                  />
                </label>
              </div>

              <div className="quote-followup-form-footer">
                <p role="status" className={followUpMessage.startsWith("Nije") ? "followup-save-message is-error" : "followup-save-message"}>{followUpMessage}</p>
                {canUpdateQuote && <button type="button" onClick={saveFollowUp} disabled={savingFollowUp} className="followup-save-button">
                  {savingFollowUp ? "Spremanje…" : "Sačuvaj praćenje i ishod"}
                </button>}
              </div>

              <div className="quote-contact-history">
                <div className="quote-contact-history-heading">
                  <div>
                    <h4>Historija kontakata</h4>
                    <p>Svaki poziv, e-mail ili sastanak ostaje evidentiran uz ovu ponudu.</p>
                  </div>
                  <span className="quote-contact-history-count">{(selectedQuote.historijaKontakata || []).length} zapisa</span>
                </div>

                <div className="quote-contact-form-grid">
                  <label>
                    Datum kontakta
                    <input
                      type="date"
                      value={contactForm.datum}
                      onChange={(event) => setContactForm((current) => ({ ...current, datum: event.target.value }))}
                    />
                  </label>
                  <label>
                    Način kontakta
                    <select
                      value={contactForm.kanal}
                      onChange={(event) => setContactForm((current) => ({ ...current, kanal: event.target.value as ContactChannel }))}
                    >
                      <option value="email">E-mail</option>
                      <option value="telefon">Telefon</option>
                      <option value="sastanak">Sastanak</option>
                      <option value="drugo">Drugo</option>
                    </select>
                  </label>
                  <label>
                    Kontakt osoba
                    <input
                      type="text"
                      maxLength={120}
                      value={contactForm.kontaktOsoba}
                      onChange={(event) => setContactForm((current) => ({ ...current, kontaktOsoba: event.target.value }))}
                      placeholder="Ime osobe kod kupca"
                    />
                  </label>
                  <label>
                    Rezultat kontakta
                    <select
                      value={contactForm.ishod}
                      onChange={(event) => setContactForm((current) => ({ ...current, ishod: event.target.value as ContactOutcome }))}
                    >
                      <option value="no_response">Nema odgovora</option>
                      <option value="requested_change">Traži izmjene</option>
                      <option value="requested_info">Traži dodatne informacije</option>
                      <option value="negotiation">Pregovori</option>
                      <option value="positive">Pozitivan odgovor</option>
                      <option value="negative">Negativan odgovor</option>
                      <option value="other">Drugo</option>
                    </select>
                  </label>
                  <label className="quote-contact-note-field">
                    Bilješka o kontaktu
                    <textarea
                      rows={3}
                      maxLength={2000}
                      value={contactForm.napomena}
                      onChange={(event) => setContactForm((current) => ({ ...current, napomena: event.target.value }))}
                      placeholder="Šta je dogovoreno, koji je odgovor kupca i šta je sljedeći korak?"
                    />
                  </label>
                </div>
                <div className="quote-contact-form-footer">
                  <p role="status" className={contactMessage.startsWith("Kontakt nije") || contactMessage.startsWith("Unesi") ? "followup-save-message is-error" : "followup-save-message"}>{contactMessage}</p>
                  {canUpdateQuote && <button type="button" onClick={() => void saveContactHistoryEntry()} disabled={savingContact} className="followup-save-button">
                    {savingContact ? "Spremanje kontakta…" : "＋ Sačuvaj kontakt u historiju"}
                  </button>}
                </div>

                {(selectedQuote.historijaKontakata || []).length === 0 ? (
                  <div className="quote-contact-history-empty">Još nema evidentiranih kontakata za ovu ponudu.</div>
                ) : (
                  <ol className="quote-contact-timeline">
                    {[...(selectedQuote.historijaKontakata || [])]
                      .sort((a, b) => (getDateOnly(b.datum)?.getTime() || 0) - (getDateOnly(a.datum)?.getTime() || 0))
                      .map((entry) => (
                        <li key={entry.id} className="quote-contact-timeline-item">
                          <div className="quote-contact-timeline-marker" aria-hidden="true" />
                          <div className="quote-contact-timeline-content">
                            <div className="quote-contact-timeline-topline">
                              <strong>{formatDate(entry.datum)} · {getContactChannelName(entry.kanal)}</strong>
                              <span>{getContactOutcomeName(entry.ishod)}</span>
                            </div>
                            <div className="quote-contact-timeline-person">{entry.kontaktOsoba || "Kontakt osoba nije navedena"} · Evidentirao: {entry.evidentirao || "Korisnik"}</div>
                            {entry.napomena && <p>{entry.napomena}</p>}
                          </div>
                        </li>
                      ))}
                  </ol>
                )}
              </div>
            </section>
          )}

          {/* ======================================
              POZICIJE PONUDE
             ====================================== */}

          <div
            style={{
              overflowX:
                "auto",
            }}
          >

            <table
              style={{
                width:
                  "100%",
                borderCollapse:
                  "collapse",
              }}
            >

              <thead>

                <tr
                  style={{
                    background:
                      "#f8fafc",
                  }}
                >

                  <th
                    style={
                      tdStyle
                    }
                  >
                    Br.
                  </th>

                  <th
                    style={
                      tdStyle
                    }
                  >
                    Šifra
                  </th>

                  <th
                    style={
                      tdStyle
                    }
                  >
                    Naziv
                  </th>

                  <th
                    style={
                      tdStyle
                    }
                  >
                    Količina
                  </th>

                  <th
                    style={
                      tdStyle
                    }
                  >
                    Cijena / kom
                  </th>

                  <th
                    style={
                      tdStyle
                    }
                  >
                    Ukupno
                  </th>

                </tr>

              </thead>

              <tbody>

                {
                  selectedQuote
                    .positions
                    ?.map(
                      (
                        position,
                      ) => (

                        <tr
                          key={`${selectedQuote.id}-${position.positionNumber}`}
                          style={{
                            borderTop:
                              "1px solid #e2e8f0",
                          }}
                        >

                          {/* BROJ POZICIJE */}

                          <td
                            style={
                              tdStyle
                            }
                          >
                            {
                              position.positionNumber
                            }
                          </td>

                          {/* ŠIFRA */}

                          <td
                            style={
                              tdStyle
                            }
                          >
                            <strong>
                              {
                                position.code
                              }
                            </strong>
                          </td>

                          {/* NAZIV */}

                          <td
                            style={
                              tdStyle
                            }
                          >
                            {
                              position.name
                            }
                          </td>

                          {/* KOLIČINA */}

                          <td
                            style={
                              tdStyle
                            }
                          >
                            {
                              position.quantity
                            }
                          </td>

                          {/* CIJENA PO KOMADU */}

                          <td
                            style={
                              tdStyle
                            }
                          >
                            {
                              formatMoney(
                                position.sellingPricePerPiece,
                              )
                            }
                          </td>

                          {/* UKUPNO */}

                          <td
                            style={{
                              ...tdStyle,
                              fontWeight:
                                700,
                            }}
                          >
                            {
                              formatMoney(
                                position.totalSellingPrice,
                              )
                            }
                          </td>

                        </tr>

                      ),
                    )
                }

              </tbody>

            </table>

          </div>

          {/* ======================================
              UKUPNI IZNOSI
             ====================================== */}

          <div
            style={{
              display:
                "flex",
              justifyContent:
                "flex-end",
              marginTop:
                "24px",
            }}
          >

            <div
              style={{
                minWidth:
                  "420px",
                background:
                  "#f8fafc",
                borderRadius:
                  "10px",
                padding:
                  "18px",
              }}
            >

              {/* UKUPNI TROŠAK */}

              <div
                style={{
                  display:
                    "flex",
                  justifyContent:
                    "space-between",
                  marginBottom:
                    "10px",
                }}
              >

                <span>
                  Ukupni trošak
                </span>

                <strong>
                  {
                    formatMoney(
                      selectedQuote.totalCost,
                    )
                  }
                </strong>

              </div>

              {/* MARŽA */}

              <div
                style={{
                  display:
                    "flex",
                  justifyContent:
                    "space-between",
                  marginBottom:
                    "10px",
                }}
              >

                <span>
                  Marža
                </span>

                <strong>
                  {
                    Number(
                      selectedQuote.marginPercent ||
                        0,
                    ).toFixed(2)
                  }
                  %
                </strong>

              </div>

              {/* PRODAJNA VRIJEDNOST */}

              <div
                style={{
                  display:
                    "flex",
                  justifyContent:
                    "space-between",
                  borderTop:
                    "1px solid #cbd5e1",
                  paddingTop:
                    "12px",
                  fontSize:
                    "18px",
                }}
              >

                <span>
                  Ukupna prodajna
                  vrijednost
                </span>

                <strong>
                  {
                    formatMoney(
                      selectedQuote.totalSellingPrice,
                    )
                  }
                </strong>

              </div>

            </div>

          </div>

        </div>

      )}

      {showTgExportModal && selectedQuote && (
        <div
          className="quote-tg-modal-backdrop"
          role="presentation"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setShowTgExportModal(false)
          }}
        >
          <section
            className="quote-tg-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="quote-tg-modal-title"
          >
            <div className="quote-tg-modal-heading">
              <span className="quote-tg-modal-icon" aria-hidden="true">%</span>
              <div>
                <h2 id="quote-tg-modal-title">Izvoz PDF TG</h2>
                <p>Unesi dodatnu maržu koja će se dodati na postojeće prodajne cijene.</p>
              </div>
            </div>

            <form onSubmit={(event) => void handleExportPdfTg(event)}>
              <label className="quote-tg-margin-field">
                Dodatna marža (%)
                <input
                  type="text"
                  inputMode="decimal"
                  autoFocus
                  value={tgMarkupPercentText}
                  onChange={(event) => {
                    setTgMarkupPercentText(event.target.value)
                    if (tgExportError) setTgExportError("")
                  }}
                  placeholder="npr. 15"
                  aria-label="Dodatna marža u procentima"
                  aria-invalid={Boolean(tgExportError)}
                />
              </label>
              <p className="quote-tg-modal-example">Primjer: postojeća cijena 100,00 € uz dodatnu maržu od 15% postaje 115,00 €.</p>
              <p className="quote-tg-modal-note">Dodatak važi samo za ovaj PDF TG. Originalna ponuda i cijene u bazi ostaju nepromijenjene.</p>
              {tgExportError && <p className="quote-tg-modal-error" role="alert">{tgExportError}</p>}
              <div className="quote-tg-modal-actions">
                <button type="button" className="secondary-button" onClick={() => setShowTgExportModal(false)}>Odustani</button>
                <button type="submit" className="primary-button" disabled={tgExporting}>Generiši PDF TG</button>
              </div>
            </form>
          </section>
        </div>
      )}

    </div>
  )
}


/*
 * ==========================================
 * EXPORT
 * ==========================================
 */
export default Quotes