import {
  useEffect,
  useMemo,
  useState,
  useRef,
  type ClipboardEvent,
  type ChangeEvent,
  type FormEvent,
} from "react"

import * as XLSX from "xlsx"
import { useLocation, useNavigate } from "react-router-dom"

import { useAuth } from "../auth/AuthContext"
import { api } from "../api"
import { LocalizedNumberInput } from "../components/LocalizedNumberInput"
import { formatLocalizedNumber, parseLocalizedNumber } from "../utils/numericInput"
import { getQuoteExportCostBreakdown } from "../utils/quoteExportCosts"
import { extractUniversalPdfInquiry } from "../utils/universalPdfInquiryParser"

import "./NewQuote.css"

import {
  addPosition as addPositionToDatabase,
  getPositionByCode,
  updatePosition as updatePositionInDatabase,
} from "../services/positions"

import {
  getSurfaceProtections,
  getHeatTreatments,
  getOtherOperations,
  type SurfaceProtection,
  type HeatTreatment,
  type OtherOperation,
} from "../services/treatments"

import {
  getCustomers,
  type Customer,
} from "../services/customers"

import {
  addQuote,
  getAllQuotes,
  getQuoteById,
  updateQuote,
  type Quote,
} from "../services/quotes"

import {
  getGeneralSettings,
  type GeneralSettings,
} from "../services/settings"

import {
  getMaterials,
  INITIAL_MATERIALS,
  type MaterialRecord,
} from "../services/materials"

import {
  getMachines,
  INITIAL_MACHINES,
  type MachineRecord,
} from "../services/machines"
import {
  deleteQuoteDraft,
  getQuoteDraft,
  listQuoteDrafts,
  saveQuoteDraft,
  type QuoteDraftRecord,
} from "../services/quoteDrafts"


type Shape =
  | "rectangular"
  | "cylinder"
  | "hexagon"
  | "octagon"
  | "l-profile"
  | "i-profile"
  | "h-profile"
  | "u-profile"
  | "t-profile"
  | "square-tube"
  | "rectangular-tube"
  | "round-tube"
  | "custom-profile"

type StaffelMode = "shared" | "per-position"

type PositionLookupStatus =
  | "loading"
  | "found"
  | "found-skipped"
  | "not-found"
  | "error"

function normalizePositionLookupCode(code: string): string {
  return code.trim().toLocaleUpperCase()
}

type PreviousQuotePrice = {
  quoteId?: string
  quoteNumber: string
  dateValue: unknown
  dateMillis: number
  customerName: string
  quantity: number
  pricePerPiece: number
}

function getDateMillis(value: unknown): number {
  if (!value) return 0

  try {
    const dateLike = value as { toDate?: () => Date }
    const date =
      typeof dateLike.toDate === "function"
        ? dateLike.toDate()
        : value instanceof Date
          ? value
          : new Date(value as string | number)

    const millis = date.getTime()
    return Number.isFinite(millis) ? millis : 0
  } catch {
    return 0
  }
}

function formatPreviousPriceDate(value: unknown): string {
  const millis = getDateMillis(value)
  if (!millis) return "datum nije poznat"

  return new Date(millis).toLocaleDateString("bs-BA")
}

function formatPreviousPriceMoney(value: number): string {
  return `${value.toLocaleString("bs-BA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} €`
}

function formatProfitabilityMoney(value: number): string {
  return `${Number(value || 0).toLocaleString("bs-BA", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })} €`
}


function formatQuotePreparationClock(durationMs: number): string {
  const totalSeconds = Math.max(0, Math.floor((Number(durationMs) || 0) / 1000))
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  return [hours, minutes, seconds]
    .map((value) => String(value).padStart(2, "0"))
    .join(":")
}

const QUOTE_PREPARATION_TIMER_STORAGE_KEY = "cnc_quote_preparation_started_at"
const QUOTE_PREPARATION_LAST_NEW_TOKEN_STORAGE_KEY = "cnc_quote_preparation_last_new_token"


const DEFAULT_STAFFEL_QUANTITIES = "1, 2, 3, 5, 10, 15, 20"

/** Parses customer-requested quantity breaks, removes duplicates and sorts ascending. */
function parseStaffelQuantities(input: string): number[] {
  const quantities = input
    .split(/[,;\n]+/)
    .map((value) => Number(value.trim()))
    .filter((value) => Number.isInteger(value) && value > 0)

  return [...new Set(quantities)].sort((a, b) => a - b)
}

function hasInvalidStaffelQuantity(input: string): boolean {
  const parts = input.split(/[,;\n]+/).map((value) => value.trim()).filter(Boolean)
  return parts.some((value) => !/^\d+$/.test(value) || Number(value) <= 0)
}

type Position = {
  positionNumber: string
  code: string
  name: string
  quantity: number
  /** Marža za ovu poziciju; undefined nasljeđuje staru/globalnu zadanu vrijednost. */
  marginPercent?: number
  /** Transport se podešava nezavisno za svaku poziciju. */
  transportIncluded?: boolean
  transportPricePerKg?: number
  /** Dodatne količine za ovu poziciju kada se koriste pojedinačni Staffelpreise. */
  staffelQuantityText: string

  shape: Shape

  length: number
  width: number
  height: number
  diameter: number
  /** Ručno unesena ulazna težina sirovog komada; 0 znači da se računa iz dimenzija. */
  rawWeightKg: number
  /** Tekstualna vrijednost čuva decimalni unos dok korisnik još tipka. */
  rawWeightInput: string
  /** Ručno unesena stvarna težina nakon obrade; 0 koristi ulaznu težinu kao osnovu. */
  outputWeightKg: number
  /** Tekst u inputu čuva decimalni unos dok korisnik još tipka. */
  outputWeightInput: string
  // Dodatne dimenzije profila i šupljih presjeka (mm).
  thickness: number
  flangeThickness: number
  webThickness: number
  customCrossSectionArea: number

  material: string

  programmingMinutes: number
  setupMinutes: number
  machiningMinutes: number

  machine: string
  // Satnice korištene u originalnoj ponudi. Pri uređivanju stare ponude
  // zadržavamo ih; nova ponuda koristi aktuelne satnice iz baze.
  machineProgrammingRate?: number
  machineSetupRate?: number
  machineMachiningRate?: number

  surfaceProtectionId: string
  surfaceProtectionName: string

  heatTreatmentId: string
  heatTreatmentName: string

  otherOperationId: string
  otherOperationName: string

  otherCost1Description: string
  otherCost1Amount: number
  otherCost2Description: string
  otherCost2Amount: number
  ensatCount: number

  inspectionCostSeries: number
}

type Material = {
  name: string
  density: number
  pricePerKg: number
}

// Vrijednosti iz starog kalkulatora ostaju sigurnosni fallback dok se
// početni materijali ne prenesu u Firestore. Nakon unosa u bazu, cijene i
// gustine iz Firestorea imaju prednost.
const defaultMaterials: Record<string, Material> = Object.fromEntries(
  INITIAL_MATERIALS.map((item) => [
    item.oznaka,
    {
      name: item.naziv || item.oznaka,
      density: Number(item.gustina) || 7850,
      pricePerKg: Number(item.cijenaPoKg) || 0,
    },
  ]),
) as Record<string, Material>

type MachineRate = {
  name: string
  programmingHourlyRate: number
  setupHourlyRate: number
  machiningHourlyRate: number
}

// Kompatibilni fallback dok se početne mašine ne unesu u Firestore.
// Oznake su iste kao u ranije spremljenim pozicijama, tako da postojeće
// ponude i dalje mogu pronaći odgovarajuću mašinu.
const defaultMachines: Record<string, MachineRate> = Object.fromEntries(
  INITIAL_MACHINES.map((item) => [
    item.oznaka,
    {
      name: item.naziv,
      programmingHourlyRate: Number.isFinite(Number(item.satnicaProgramiranja))
        ? Math.max(0, Number(item.satnicaProgramiranja))
        : 35,
      setupHourlyRate: Number(item.satnicaPripreme) || 0,
      machiningHourlyRate: Number(item.satnicaObrade) || 0,
    },
  ]),
) as Record<string, MachineRate>

function createEmptyPosition(
  positionNumber: string,
): Position {
  return {
    positionNumber,

    code: "",
    name: "",
    quantity: 1,
    staffelQuantityText: "",

    shape: "rectangular",

    length: 0,
    width: 0,
    height: 0,
    diameter: 0,
    rawWeightKg: 0,
    rawWeightInput: "",
    outputWeightKg: 0,
    outputWeightInput: "",
    thickness: 0,
    flangeThickness: 0,
    webThickness: 0,
    customCrossSectionArea: 0,

    material: "S235",

    programmingMinutes: 0,
    setupMinutes: 0,
    machiningMinutes: 0,

    machine: "Haas_UMC500",

    surfaceProtectionId: "",
    surfaceProtectionName: "",

    heatTreatmentId: "",
    heatTreatmentName: "",

    otherOperationId: "",
    otherOperationName: "",
    otherCost1Description: "",
    otherCost1Amount: 0,
    otherCost2Description: "",
    otherCost2Amount: 0,
    ensatCount: 0,

    inspectionCostSeries: 0,
  }
}

type PositionDimensionField =
  | "length"
  | "width"
  | "height"
  | "diameter"
  | "thickness"
  | "flangeThickness"
  | "webThickness"
  | "customCrossSectionArea"

type ShapeDimensions = Pick<
  Position,
  PositionDimensionField | "shape"
>

/**
 * Provjerava da li su unesene dimenzije dovoljne za pouzdan obračun.
 * Profile računamo kao idealne presjeke, bez radijusa i kosina.
 */
function getShapeDimensionWarning(position: ShapeDimensions): string {
  const dimensions = [
    position.length,
    position.width,
    position.height,
    position.diameter,
    position.thickness,
    position.flangeThickness,
    position.webThickness,
    position.customCrossSectionArea,
  ].map((value) => Number(value) || 0)

  // Na potpuno praznoj novoj poziciji ne prikazujemo upozorenje.
  if (!dimensions.some((value) => value !== 0)) return ""

  const length = Number(position.length) || 0
  const width = Number(position.width) || 0
  const height = Number(position.height) || 0
  const diameter = Number(position.diameter) || 0
  const thickness = Number(position.thickness) || 0
  const flangeThickness = Number(position.flangeThickness) || 0
  const webThickness = Number(position.webThickness) || 0
  const customCrossSectionArea = Number(position.customCrossSectionArea) || 0

  if (length <= 0) return "Unesi dužinu komada/profila veću od 0 mm."

  switch (position.shape) {
    case "rectangular":
      if (width <= 0 || height <= 0) return "Za pravougaoni komad unesi širinu i visinu veću od 0 mm."
      break
    case "cylinder":
      if (diameter <= 0) return "Unesi vanjski prečnik veći od 0 mm."
      break
    case "hexagon":
    case "octagon":
      if (width <= 0) return "Unesi širinu preko ravnih strana veću od 0 mm."
      break
    case "l-profile":
      if (width <= 0 || height <= 0 || thickness <= 0) return "Za L-profil unesi širinu, visinu i debljinu krakova."
      if (thickness >= Math.min(width, height)) return "Debljina L-profila mora biti manja od širine i visine krakova."
      break
    case "i-profile":
    case "h-profile":
    case "u-profile":
      if (width <= 0 || height <= 0 || flangeThickness <= 0 || webThickness <= 0) {
        return "Unesi širinu, ukupnu visinu, debljinu prirubnice i debljinu rebra."
      }
      if (2 * flangeThickness >= height) return "Dvije prirubnice moraju biti tanje od ukupne visine profila."
      if (webThickness >= width) return "Debljina rebra mora biti manja od širine profila."
      break
    case "t-profile":
      if (width <= 0 || height <= 0 || flangeThickness <= 0 || webThickness <= 0) {
        return "Za T-profil unesi širinu, ukupnu visinu, debljinu prirubnice i debljinu rebra."
      }
      if (flangeThickness >= height) return "Debljina prirubnice mora biti manja od ukupne visine T-profila."
      if (webThickness >= width) return "Debljina rebra mora biti manja od širine profila."
      break
    case "square-tube":
      if (width <= 0 || thickness <= 0) return "Za kvadratnu cijev unesi vanjsku stranicu i debljinu stijenke."
      if (2 * thickness >= width) return "Debljina stijenke mora biti manja od polovine vanjske stranice."
      break
    case "rectangular-tube":
      if (width <= 0 || height <= 0 || thickness <= 0) return "Za pravougaonu cijev unesi vanjsku širinu, visinu i debljinu stijenke."
      if (2 * thickness >= Math.min(width, height)) return "Debljina stijenke mora biti manja od polovine manje vanjske dimenzije."
      break
    case "round-tube":
      if (diameter <= 0 || thickness <= 0) return "Za okruglu cijev unesi vanjski prečnik i debljinu stijenke."
      if (2 * thickness >= diameter) return "Debljina stijenke mora biti manja od polovine vanjskog prečnika."
      break
    case "custom-profile":
      if (customCrossSectionArea <= 0) return "Unesi površinu poprečnog presjeka iz crteža ili kataloga, veću od 0 mm²."
      break
    default:
      return "Odabrani oblik nije podržan za obračun materijala."
  }

  return ""
}

/** Površina poprečnog presjeka u mm² za idealiziranu geometriju sirovine. */
function calculateCrossSectionAreaMm2(position: ShapeDimensions): number {
  if (getShapeDimensionWarning(position)) return 0

  const width = Math.max(0, Number(position.width) || 0)
  const height = Math.max(0, Number(position.height) || 0)
  const diameter = Math.max(0, Number(position.diameter) || 0)
  const thickness = Math.max(0, Number(position.thickness) || 0)
  const flangeThickness = Math.max(0, Number(position.flangeThickness) || 0)
  const webThickness = Math.max(0, Number(position.webThickness) || 0)
  const customCrossSectionArea = Math.max(0, Number(position.customCrossSectionArea) || 0)

  let area = 0
  switch (position.shape) {
    case "rectangular":
      area = width * height
      break
    case "cylinder":
      area = Math.PI * Math.pow(diameter / 2, 2)
      break
    // Šestkant i osmokant koriste širinu preko ravnih strana (across flats).
    case "hexagon":
      area = (Math.sqrt(3) / 2) * Math.pow(width, 2)
      break
    case "octagon":
      area = 2 * (Math.sqrt(2) - 1) * Math.pow(width, 2)
      break
    case "l-profile":
      // Dva kraka, umanjena za preklapanje u uglu.
      area = width * thickness + height * thickness - Math.pow(thickness, 2)
      break
    case "i-profile":
    case "h-profile":
    case "u-profile":
      area = 2 * width * flangeThickness + (height - 2 * flangeThickness) * webThickness
      break
    case "t-profile":
      area = width * flangeThickness + (height - flangeThickness) * webThickness
      break
    case "square-tube": {
      const innerSide = width - 2 * thickness
      area = Math.pow(width, 2) - Math.pow(innerSide, 2)
      break
    }
    case "rectangular-tube": {
      const innerWidth = width - 2 * thickness
      const innerHeight = height - 2 * thickness
      area = width * height - innerWidth * innerHeight
      break
    }
    case "round-tube": {
      const innerDiameter = diameter - 2 * thickness
      area = (Math.PI / 4) * (Math.pow(diameter, 2) - Math.pow(innerDiameter, 2))
      break
    }
    case "custom-profile":
      area = customCrossSectionArea
      break
    default:
      area = 0
  }

  return Number.isFinite(area) ? Math.max(0, area) : 0
}

function calculateStockVolumeMm3(position: ShapeDimensions): number {
  if (getShapeDimensionWarning(position)) return 0
  const length = Math.max(0, Number(position.length) || 0)
  return calculateCrossSectionAreaMm2(position) * length
}


/**
 * Pretvara podatke kopirane iz Excela (tab-separated) u pozicije.
 * Podržava postojeći format aplikacije, kao i kupčev SAP No. / Text /
 * Required Qty / ... / Price Scale format.
 */
function parseExcelPaste(text: string): InquiryImportRow[] {
  const lines = text
    .replace(/\r\n/g, "\n")
    .replace(/\r/g, "\n")
    .split("\n")
    .filter((line) => line.trim() !== "")
  const grid = lines.map((line) => line.split("\t").map((cell) => cell.trim()))

  // Prvo koristimo zaglavlja: to podržava i kupčev format
  // SAP No. | Text | Required Qty | Unit | ... | Price Scale.
  const headerAwareRows = parseInquiryGrid(grid, "excel")
  if (headerAwareRows.length > 0) return headerAwareRows

  // Rezervna podrška za direktno lijepljenje bez zaglavlja.
  return grid.map((columns, index) => {
    const usesCustomerLayout = columns.length >= 7
    const quantityText = (columns[usesCustomerLayout ? 2 : 3] ?? "")
      .trim()
      .replace(/\s/g, "")
      .replace(",", ".")
    const parsedQuantity = Number(quantityText)

    return {
      positionNumber: usesCustomerLayout
        ? String(index + 1).padStart(3, "0")
        : columns[0]?.trim() || String(index + 1).padStart(3, "0"),
      code: columns[usesCustomerLayout ? 0 : 1]?.trim() || "",
      name: columns[usesCustomerLayout ? 1 : 2]?.trim() || "",
      quantity:
        Number.isFinite(parsedQuantity) && parsedQuantity > 0
          ? parsedQuantity
          : 1,
      staffelQuantityText: usesCustomerLayout ? (columns[6] ?? "").trim() : "",
    }
  }).filter((row) => row.code.trim() !== "")
}

type InquiryImportRow = {
  positionNumber: string
  code: string
  name: string
  quantity: number
  /** Opcionalna kolona Price Scale iz kupčevog upita, npr. 1;2;4. */
  staffelQuantityText?: string
}

type ImportPositionDatabaseCheck = {
  status: "found" | "new" | "error" | "missing-code"
  savedPosition?: any
  databaseName: string
  nameMismatch: boolean
  useDatabaseData: boolean
}

type ImportedPositionReview = {
  databaseName: string
  importedName: string
  nameMismatch: boolean
  useDatabaseData: boolean
  status: "found" | "new" | "error" | "missing-code"
}

function normalizeComparablePositionName(value: unknown): string {
  return String(value ?? "")
    .trim()
    .toLocaleLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/ß/g, "ss")
    .replace(/[^a-z0-9]+/g, " ")
    .trim()
    .replace(/\s+/g, " ")
}

type InquiryFormat = "excel" | "pdf"

function normalizeInquiryHeader(value: unknown): string {
  return String(value ?? "")
    .trim()
    .toLocaleLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9]/g, "")
}

function getInquiryHeaderMap(row: string[]) {
  const aliases = {
    positionNumber: [
      "br", "broj", "nr", "pos", "positionsnr", "posnr", "brojpozicije", "brpozicije", "pozbroj",
      "position", "positionno", "positionnumber", "posnr",
      "posnummer", "positionsnummer", "itemno", "itemnumber",
    ],
    code: [
      "sifra", "sifraartikla", "artikalsifra", "artikelnr",
      "artnr", "artikelnummer", "teilnummer", "zeichnungsnr", "zeichnungsnummer",
      "partno", "partnumber", "itemcode", "articlecode", "code",
      "artikelcode", "material", "materialnr", "materialnummer", "materialno", "matnr", "sapno", "sapnumber", "sapartikelnummer",
    ],
    name: [
      "naziv", "nazivartikla", "opis", "description", "bezeichnung",
      "benennung", "artikelbezeichnung", "artikeldescription", "materialbeschreibung", "materialbezeichnung", "name",
      "designation", "itemdescription", "kurztext", "kurzbeschreibung",
      "kurzbezeichnung", "artikeltext", "beschreibung", "produktbezeichnung", "text",
    ],
    quantity: [
      "kolicina", "qty", "quantity", "menge", "anzahl", "stuck",
      "stueck", "stk", "pcs", "pieces", "piece", "bedarf", "cantidad", "requiredqty", "requiredquantity",
    ],
  }

  const normalized = row.map(normalizeInquiryHeader)
  const find = (names: string[]) => normalized.findIndex((cell) => names.includes(cell))

  return {
    positionNumber: find(aliases.positionNumber),
    code: find(aliases.code),
    name: find(aliases.name),
    quantity: find(aliases.quantity),
    priceScale: find(["pricescale", "pricescaledodatnekolicine", "dodatnekolicine", "staffel", "staffelpreise", "staffelmengen", "preisstaffel", "quantityscale", "quantitybreaks"]),
  }
}

function parseInquiryQuantity(value: unknown): number | null {
  let raw = String(value ?? "").trim().replace(/\s/g, "")
  if (!raw) return null
  raw = raw.replace(/[^\d,.-]/g, "")
  if (!raw || !/\d/.test(raw)) return null

  // Bosanski format hiljada može koristiti tačku, a decimalni zarez.
  if (/^\d{1,3}(\.\d{3})+$/.test(raw)) {
    raw = raw.replace(/\./g, "")
  } else if (raw.includes(",") && raw.includes(".")) {
    if (raw.lastIndexOf(",") > raw.lastIndexOf(".")) {
      raw = raw.replace(/\./g, "").replace(",", ".")
    } else {
      raw = raw.replace(/,/g, "")
    }
  } else if (raw.includes(",")) {
    raw = raw.replace(",", ".")
  }

  const quantity = Number(raw)
  return Number.isFinite(quantity) && quantity > 0 ? quantity : null
}

type ExcelColumnMapping = {
  positionNumber: number
  code: number
  name: number
  quantity: number
  /** Optional mapping; omitted in older saved customer import profiles. */
  priceScale?: number
}


type SavedExcelImportProfile = {
  customerId: string
  customerName: string
  format: "excel"
  dataStartRow: number
  mapping: ExcelColumnMapping
  sourceFileName?: string
  savedAt?: string
}

const IMPORT_PROFILE_STORAGE_PREFIX = "cnc_quote_import_profile_excel_"

function getImportProfileDocumentId(customerId: string): string {
  // Customer IDs are normally Firebase IDs, but encode defensively so
  // the value is always safe for a single Firestore document path segment.
  return `importProfile_excel_${encodeURIComponent(customerId).replace(/%/g, "_")}`
}

function isSavedExcelImportProfile(
  value: unknown,
  expectedCustomerId: string,
): value is SavedExcelImportProfile {
  if (!value || typeof value !== "object") return false
  const profile = value as Partial<SavedExcelImportProfile>
  const mapping = profile.mapping
  if (
    profile.customerId !== expectedCustomerId ||
    profile.format !== "excel" ||
    !Number.isInteger(profile.dataStartRow) ||
    Number(profile.dataStartRow) < 0 ||
    !mapping ||
    !Number.isInteger(mapping.code) || Number(mapping.code) < 0 ||
    !Number.isInteger(mapping.name) || Number(mapping.name) < 0 ||
    !Number.isInteger(mapping.quantity) || Number(mapping.quantity) < 0 ||
    (mapping.priceScale !== undefined && mapping.priceScale !== -1 && (!Number.isInteger(mapping.priceScale) || mapping.priceScale < 0))
  ) {
    return false
  }

  return mapping.positionNumber === -1 || (
    Number.isInteger(mapping.positionNumber) && Number(mapping.positionNumber) >= 0
  )
}

function getLocalExcelImportProfile(customerId: string): SavedExcelImportProfile | null {
  try {
    const raw = window.localStorage.getItem(`${IMPORT_PROFILE_STORAGE_PREFIX}${customerId}`)
    if (!raw) return null
    const parsed: unknown = JSON.parse(raw)
    return isSavedExcelImportProfile(parsed, customerId) ? parsed : null
  } catch {
    return null
  }
}

async function loadSavedExcelImportProfile(
  customerId: string,
): Promise<SavedExcelImportProfile | null> {
  const localProfile = getLocalExcelImportProfile(customerId)

  try {
    const remoteProfile: unknown = await api.get(`/settings/${getImportProfileDocumentId(customerId)}`)
    if (isSavedExcelImportProfile(remoteProfile, customerId)) {
      try {
        window.localStorage.setItem(
          `${IMPORT_PROFILE_STORAGE_PREFIX}${customerId}`,
          JSON.stringify(remoteProfile),
        )
      } catch {
        // A full or disabled local storage must not block importing.
      }
      return remoteProfile
    }
  } catch (error) {
    console.warn("Nije moguće učitati zajednički format za Excel upit:", error)
  }

  return localProfile
}

/*
 * Prepoznavanje kupca iz sadržaja Excel/PDF upita.
 * Uparujemo naziv firme kao cjelovitu frazu, uz ignorisanje čestih
 * pravnih nastavaka (GmbH, AG, KG i slično). Email iz baze je dodatni
 * jak signal. Ako više kupaca ima sličan rezultat, ne biramo nasumično.
 */
type CustomerRecognitionResult = {
  matchedCustomer: Customer | null
  suggestions: Customer[]
  reason: string
}

function normalizeCustomerText(value: unknown): string {
  return String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLocaleLowerCase()
    .replace(/&/g, " ")
    .replace(/[^a-z0-9@.+_-]+/g, " ")
    .replace(/\s+/g, " ")
    .trim()
}

function normalizeCustomerCompanyName(value: unknown): string {
  const legalSuffixes = new Set([
    "gmbh", "ag", "kg", "co", "ltd", "limited", "inc", "llc",
    "srl", "sro", "sa", "spa", "bv", "nv", "ohg", "gbr", "mbh",
    "corp", "corporation", "company",
  ])

  return normalizeCustomerText(value)
    .split(" ")
    .filter((token) => token && !legalSuffixes.has(token))
    .join(" ")
    .trim()
}

function containsWholePhrase(text: string, phrase: string): boolean {
  if (!text || !phrase) return false
  return (` ${text} `).includes(` ${phrase} `)
}

function detectCustomerFromInquiry(
  documentText: string,
  fileName: string,
  customers: Customer[],
): CustomerRecognitionResult {
  const normalizedDocument = normalizeCustomerCompanyName(documentText)
  const normalizedFileName = normalizeCustomerCompanyName(fileName)
  const rawDocument = String(documentText ?? "").toLocaleLowerCase()
  const ranked: Array<{ customer: Customer; score: number; reason: string }> = []

  for (const customer of customers) {
    if (!customer.id || customer.aktivan === false) continue

    const companyName = normalizeCustomerCompanyName(customer.naziv)
    const companyTokens = companyName.split(" ").filter(Boolean)
    let score = 0
    let reason = ""

    const nameCanAutoMatch = companyTokens.length >= 2 || (companyTokens[0]?.length ?? 0) >= 7
    if (companyName && nameCanAutoMatch && containsWholePhrase(normalizedDocument, companyName)) {
      score = 90 + Math.min(companyTokens.length, 5) * 2
      reason = "naziv firme u dokumentu"
    } else if (companyName && nameCanAutoMatch && containsWholePhrase(normalizedFileName, companyName)) {
      score = 76 + Math.min(companyTokens.length, 4) * 2
      reason = "naziv firme u nazivu datoteke"
    }

    const email = String((customer as Customer & { email?: string }).email ?? "").trim().toLocaleLowerCase()
    if (email && email.includes("@") && rawDocument.includes(email)) {
      score = Math.max(score, 112)
      reason = "e-mail adresa iz baze kupaca"
    }

    if (score > 0) ranked.push({ customer, score, reason })
  }

  ranked.sort((a, b) => b.score - a.score || String(a.customer.naziv).localeCompare(String(b.customer.naziv)))
  if (!ranked.length) {
    return { matchedCustomer: null, suggestions: [], reason: "" }
  }

  const best = ranked[0]
  const second = ranked[1]
  const isUniqueHighConfidence = best.score >= 88 && (!second || best.score - second.score >= 8)

  if (isUniqueHighConfidence) {
    return {
      matchedCustomer: best.customer,
      suggestions: [],
      reason: best.reason,
    }
  }

  // Kod nejasnog/ambivalentnog rezultata ponudimo najbliže kandidate,
  // ali ne mijenjamo kupca automatski.
  const suggestions = ranked
    .filter((candidate) => best.score - candidate.score <= 12)
    .slice(0, 4)
    .map((candidate) => candidate.customer)

  return { matchedCustomer: null, suggestions, reason: "" }
}

function isExcelImportProfileCompatible(
  profile: SavedExcelImportProfile,
  grid: string[][],
): boolean {
  const columnCount = Math.max(0, ...grid.map((row) => row.length))
  const mapping = profile.mapping
  return (
    profile.dataStartRow >= 0 &&
    profile.dataStartRow < grid.length &&
    mapping.code < columnCount &&
    mapping.name < columnCount &&
    mapping.quantity < columnCount &&
    (mapping.positionNumber === -1 || mapping.positionNumber < columnCount) &&
    (mapping.priceScale === undefined || mapping.priceScale === -1 || mapping.priceScale < columnCount)
  )
}

function findBestInquiryHeader(grid: string[][]): { headerIndex: number; headerMap: ExcelColumnMapping } {
  let bestIndex = -1
  let bestMap: ExcelColumnMapping = { positionNumber: -1, code: -1, name: -1, quantity: -1, priceScale: -1 }
  let bestScore = 0

  // Gledamo prvih 50 redova, jer se tabela kod upita često nalazi
  // poslije zaglavlja kupca, adrese i podataka o projektu.
  for (let index = 0; index < Math.min(grid.length, 50); index += 1) {
    const candidate = getInquiryHeaderMap(grid[index])
    const mappedCount = [candidate.positionNumber, candidate.code, candidate.name, candidate.quantity]
      .filter((column) => column >= 0).length
    if (candidate.code < 0 || (candidate.quantity < 0 && candidate.name < 0) || mappedCount < 2) continue

    // Više prepoznatih kolona ima prednost; u slučaju izjednačenja
    // preferiramo tabelu koja sadrži i broj pozicije i količinu.
    const score = mappedCount * 10 + (candidate.positionNumber >= 0 ? 2 : 0) + (candidate.quantity >= 0 ? 2 : 0) + (candidate.name >= 0 ? 1 : 0)
    if (score > bestScore) {
      bestScore = score
      bestIndex = index
      bestMap = candidate
    }
  }

  return { headerIndex: bestIndex, headerMap: bestMap }
}

function getExcelColumnLetter(index: number): string {
  let value = index + 1
  let result = ""
  while (value > 0) {
    const remainder = (value - 1) % 26
    result = String.fromCharCode(65 + remainder) + result
    value = Math.floor((value - 1) / 26)
  }
  return result
}

function parseExcelGridByMapping(
  grid: string[][],
  startRow: number,
  mapping: ExcelColumnMapping,
): InquiryImportRow[] {
  const result: InquiryImportRow[] = []
  const safeStart = Math.max(0, Math.min(Math.floor(startRow), grid.length))

  grid.slice(safeStart).forEach((row) => {
    const cell = (column: number) => column >= 0 ? String(row[column] ?? "").trim() : ""
    const code = cell(mapping.code)
    const name = cell(mapping.name)
    const quantity = parseInquiryQuantity(cell(mapping.quantity))
    if (!code || quantity === null) return

    result.push({
      positionNumber: cell(mapping.positionNumber) || String(result.length + 1).padStart(3, "0"),
      code,
      name,
      quantity,
      staffelQuantityText: cell(mapping.priceScale ?? -1),
    })
  })

  return result
}

function parseInquiryGrid(grid: string[][], format: InquiryFormat): InquiryImportRow[] {
  const rows = grid
    .map((row) => row.map((cell) => String(cell ?? "").trim()))
    .filter((row) => row.some((cell) => cell !== ""))
  if (!rows.length) return []

  // Prvo pronađemo najvjerovatniji red zaglavlja, umjesto da prihvatimo
  // prvi djelimični pogodak u gornjim podacima upita.
  const detectedHeader = findBestInquiryHeader(rows)
  let headerIndex = detectedHeader.headerIndex
  let headerMap = detectedHeader.headerMap

  // PDF redove nikada ne pokušavamo naslijepo čitati bez prepoznatog zaglavlja.
  // To je ranije pravilo lažne pozicije od broja upita, adrese i rokova isporuke.
  if (format === "pdf" && headerIndex < 0) return []

  const result: InquiryImportRow[] = []
  const dataRows = headerIndex >= 0 ? rows.slice(headerIndex + 1) : rows

  dataRows.forEach((row) => {
    const cells = row.map((cell) => cell.trim())
    if (!cells.some(Boolean)) return

    let positionNumber = ""
    let code = ""
    let name = ""
    let quantity: number | null = null
    let staffelQuantityText = ""

    if (headerIndex >= 0) {
      const valueAt = (column: number) => column >= 0 ? (cells[column] ?? "") : ""
      positionNumber = valueAt(headerMap.positionNumber)
      code = valueAt(headerMap.code)
      name = valueAt(headerMap.name)
      quantity = parseInquiryQuantity(valueAt(headerMap.quantity))
      staffelQuantityText = valueAt(headerMap.priceScale ?? -1)
    } else if (format === "excel") {
      // Bez naslova: podržavamo redoslijed iz aplikacije (Br., Šifra, Naziv, Količina),
      // kao i čest upit s tri kolone (Šifra, Naziv, Količina).
      if (cells.length >= 7) {
        code = cells[0]
        name = cells[1]
        quantity = parseInquiryQuantity(cells[2])
        staffelQuantityText = cells[6] || ""
      } else if (cells.length >= 4) {
        positionNumber = cells[0]
        code = cells[1]
        name = cells.slice(2, cells.length - 1).join(" ").trim()
        quantity = parseInquiryQuantity(cells[cells.length - 1])
      } else if (cells.length === 3) {
        code = cells[0]
        name = cells[1]
        quantity = parseInquiryQuantity(cells[2])
      } else {
        return
      }
    } else {
      // PDF može imati različit broj razdvojenih ćelija po redu.
      // Ako se čitaju četiri ili više ćelija, prva se tumači kao broj pozicije,
      // druga kao šifra, a posljednja kao količina; naziv je između njih.
      let pdfCells = cells
      if (pdfCells.length === 1) {
        pdfCells = pdfCells[0].split(/\s{2,}/).map((value) => value.trim()).filter(Boolean)
      }

      if (pdfCells.length >= 4) {
        positionNumber = pdfCells[0]
        code = pdfCells[1]
        name = pdfCells.slice(2, -1).join(" ").trim()
        quantity = parseInquiryQuantity(pdfCells[pdfCells.length - 1])
      } else if (pdfCells.length === 3) {
        code = pdfCells[0]
        name = pdfCells[1]
        quantity = parseInquiryQuantity(pdfCells[2])
      } else {
        // Rezervna heuristika za red koji je PDF izložio kao jednu tekstualnu cjelinu.
        const rawLine = pdfCells.join(" ").trim()
        const tokens = rawLine.split(/\s+/)
        const foundQuantityIndex = tokens.findIndex((token, index) =>
          index === tokens.length - 1 && parseInquiryQuantity(token) !== null,
        )
        if (foundQuantityIndex < 1) return
        const lastNumeric = tokens[foundQuantityIndex]
        quantity = parseInquiryQuantity(lastNumeric)
        if (tokens.length >= 4 && /^\d{1,4}$/.test(tokens[0])) {
          positionNumber = tokens[0]
          code = tokens[1]
          name = tokens.slice(2, -1).join(" ")
        } else {
          code = tokens[0]
          name = tokens.slice(1, -1).join(" ")
        }
      }
    }

    if (!code || quantity === null) return
    // Naslovi i ukupni redovi nisu valjane pozicije.
    const normalizedCode = normalizeInquiryHeader(code)
    if (["sifra", "artikelnummer", "artnr", "code", "artikelnr"].includes(normalizedCode)) return

    // Kada PDF ima kolonu pozicije, prihvatamo samo numerički broj pozicije.
    // Ovo sprečava da se tekst iz zaglavlja ili uslovi isporuke uvezu kao artikli.
    if (
      format === "pdf" &&
      headerMap.positionNumber >= 0 &&
      !/^\d{1,6}$/.test(positionNumber.trim())
    ) return

    result.push({
      positionNumber: positionNumber || String(result.length + 1).padStart(3, "0"),
      code: code.trim(),
      name: name.trim(),
      quantity,
      staffelQuantityText: staffelQuantityText.trim(),
    })
  })

  return result
}

function convertDatabasePosition(
  savedPosition: any,
  currentPosition: Position,
): Position {
  return {
    positionNumber:
      currentPosition.positionNumber,

    code:
      currentPosition.code,

    name:
      currentPosition.name ||
      savedPosition.name ||
      savedPosition.naziv ||
      "",

    quantity:
      currentPosition.quantity,
    staffelQuantityText:
      currentPosition.staffelQuantityText || "",

    shape:
      savedPosition.shape ||
      savedPosition.tipKomada ||
      "rectangular",

    length:
      Number(savedPosition.length ?? savedPosition.duzina) || 0,

    width:
      Number(savedPosition.width ?? savedPosition.sirina) || 0,

    height:
      Number(savedPosition.height ?? savedPosition.visina) || 0,

    diameter:
      Number(savedPosition.diameter ?? savedPosition.precnik) || 0,

    rawWeightKg:
      Number(savedPosition.rawWeightKg ?? savedPosition.ulaznaTezinaKg) || 0,
    rawWeightInput:
      Number(savedPosition.rawWeightKg ?? savedPosition.ulaznaTezinaKg) > 0
        ? String(Number(savedPosition.rawWeightKg ?? savedPosition.ulaznaTezinaKg))
        : "",

    outputWeightKg:
      Number(savedPosition.outputWeightKg ?? savedPosition.izlaznaTezinaKg) || 0,
    outputWeightInput:
      Number(savedPosition.outputWeightKg ?? savedPosition.izlaznaTezinaKg) > 0
        ? String(Number(savedPosition.outputWeightKg ?? savedPosition.izlaznaTezinaKg))
        : "",

    thickness:
      Number(savedPosition.thickness ?? savedPosition.debljina) || 0,

    flangeThickness:
      Number(savedPosition.flangeThickness ?? savedPosition.debljinaPrirubnice) || 0,

    webThickness:
      Number(savedPosition.webThickness ?? savedPosition.debljinaRebra) || 0,

    customCrossSectionArea:
      Number(savedPosition.customCrossSectionArea ?? savedPosition.povrsinaPresjekaMm2) || 0,

    material:
      savedPosition.material ||
      savedPosition.materijal ||
      "S235",

    programmingMinutes:
      Number(
        savedPosition.programmingMinutes ?? savedPosition.programiranje,
      ) || 0,

    setupMinutes:
      Number(
        savedPosition.setupMinutes ?? savedPosition.priprema,
      ) || 0,

    machiningMinutes:
      Number(
        savedPosition.machiningMinutes ?? savedPosition.obrada,
      ) || 0,

    machine:
      savedPosition.machine ||
      savedPosition.masina ||
      "Haas_UMC500",

    surfaceProtectionId:
      savedPosition.surfaceProtectionId ||
      "",

    surfaceProtectionName:
      savedPosition.surfaceProtectionName ||
      savedPosition.povrsinskaZastita ||
      "",

    heatTreatmentId:
      savedPosition.heatTreatmentId ||
      "",

    heatTreatmentName:
      savedPosition.heatTreatmentName ||
      savedPosition.termickaObrada ||
      "",

    otherOperationId:
      savedPosition.otherOperationId || "",
    otherOperationName:
      savedPosition.otherOperationName || savedPosition.ostaliZahvat || "",
    otherCost1Description:
      savedPosition.otherCost1Description || "",
    otherCost1Amount:
      Number(savedPosition.otherCost1Amount) || 0,
    otherCost2Description:
      savedPosition.otherCost2Description || "",
    otherCost2Amount:
      Number(savedPosition.otherCost2Amount) || 0,
    ensatCount:
      Number(savedPosition.ensatCount) || 0,

    inspectionCostSeries:
      Number(savedPosition.inspectionCostSeries) || 0,
  }
}

function calculatePositionCostsForSave(
  position: Position,
  marginPercent: number,
  surfaceProtections: SurfaceProtection[],
  heatTreatments: HeatTreatment[],
  otherOperations: OtherOperation[],
  generalSettings: GeneralSettings,
  materialsCatalog: Record<string, Material>,
  machinesCatalog: Record<string, MachineRate>,
  transportIncluded = true,
) {
  const material =
    materialsCatalog[position.material] ||
    defaultMaterials[position.material] ||
    defaultMaterials.S235
  const rawGeometryWarning = getShapeDimensionWarning(position)
  const crossSectionAreaMm2 = calculateCrossSectionAreaMm2(position)
  const volumeMm3 = calculateStockVolumeMm3(position)
  const volumeM3 = volumeMm3 / 1_000_000_000
  const calculatedRawWeightKg = volumeM3 * material.density
  const enteredRawWeightKg = Math.max(0, Number(position.rawWeightKg) || 0)
  const geometryWarning = enteredRawWeightKg > 0
    ? ""
    : rawGeometryWarning || (calculatedRawWeightKg <= 0
      ? "Unesi dimenzije sirovine ili ručno unesi težinu sirovog komada [kg]."
      : "")
  const weightKg = enteredRawWeightKg > 0 ? enteredRawWeightKg : calculatedRawWeightKg
  // Materijal se obračunava po ulaznoj težini (ručni unos ili dimenzije). Transport
  // i površinski/termički tretmani obračunavaju se po izlaznoj težini.
  const enteredOutputWeightKg = Number(position.outputWeightKg) || 0
  const outputWeightKg = enteredOutputWeightKg > 0 ? enteredOutputWeightKg : weightKg
  const materialCost = weightKg * material.pricePerKg
  const baseMachine =
    machinesCatalog[position.machine] ||
    defaultMachines[position.machine] ||
    defaultMachines.Haas_UMC500 ||
    { name: position.machine || "Nepoznata mašina", programmingHourlyRate: 35, setupHourlyRate: 0, machiningHourlyRate: 0 }
  const machine: MachineRate = {
    name: baseMachine.name,
    programmingHourlyRate: typeof position.machineProgrammingRate === "number" && Number.isFinite(position.machineProgrammingRate)
      ? Math.max(0, position.machineProgrammingRate)
      : baseMachine.programmingHourlyRate,
    setupHourlyRate: typeof position.machineSetupRate === "number" && Number.isFinite(position.machineSetupRate)
      ? Math.max(0, position.machineSetupRate)
      : baseMachine.setupHourlyRate,
    machiningHourlyRate: typeof position.machineMachiningRate === "number" && Number.isFinite(position.machineMachiningRate)
      ? Math.max(0, position.machineMachiningRate)
      : baseMachine.machiningHourlyRate,
  }
  const quantity = Math.max(1, Number(position.quantity) || 1)
  const effectiveMarginPercent = Number.isFinite(Number(position.marginPercent))
    ? Number(position.marginPercent)
    : marginPercent
  const effectiveTransportIncluded = typeof position.transportIncluded === "boolean"
    ? position.transportIncluded
    : transportIncluded
  const effectiveTransportPricePerKg = Number.isFinite(Number(position.transportPricePerKg))
    ? Math.max(0, Number(position.transportPricePerKg))
    : Math.max(0, Number(generalSettings.transportPricePerKg) || 0)

  // Programiranje koristi satnicu odabrane CNC mašine.
  const programmingCost = ((position.programmingMinutes / 60) * machine.programmingHourlyRate) / quantity
  const setupCost = ((position.setupMinutes / 60) * machine.setupHourlyRate) / quantity
  const machiningCost = (position.machiningMinutes / 60) * machine.machiningHourlyRate

  const inspectionCostSeries = Number(position.inspectionCostSeries) || 0
  const inspectionCost = inspectionCostSeries / quantity
  const transportCost = effectiveTransportIncluded
    ? outputWeightKg * effectiveTransportPricePerKg
    : 0

  const protection = surfaceProtections.find((item) => item.id === position.surfaceProtectionId)
  let surfaceProtectionCost = 0
  if (protection) {
    const cijena = Number(protection.cijena) || 0
    if (protection.tipCijene === "komad") surfaceProtectionCost = cijena
    if (protection.tipCijene === "kg") surfaceProtectionCost = cijena * outputWeightKg
    if (protection.tipCijene === "fiksno") surfaceProtectionCost = cijena / quantity
  }

  const treatment = heatTreatments.find((item) => item.id === position.heatTreatmentId)
  let heatTreatmentCost = 0
  if (treatment) {
    const cijena = Number(treatment.cijena) || 0
    if (treatment.tipCijene === "komad") heatTreatmentCost = cijena
    if (treatment.tipCijene === "kg") heatTreatmentCost = cijena * outputWeightKg
    if (treatment.tipCijene === "fiksno") heatTreatmentCost = cijena / quantity
  }

  const otherOperation = otherOperations.find((item) => item.id === position.otherOperationId)
  let otherOperationCost = 0
  if (otherOperation) {
    const cijena = Number(otherOperation.cijena) || 0
    if (otherOperation.tipCijene === "komad") otherOperationCost = cijena
    if (otherOperation.tipCijene === "kg") otherOperationCost = cijena * weightKg
    if (otherOperation.tipCijene === "fiksno") otherOperationCost = cijena / quantity
  }

  const ensatCount = Math.max(0, Number(position.ensatCount) || 0)
  const ensatCostPerPiece = ensatCount * (Number(generalSettings.ensatPricePerPiece) || 0)
  const otherCost1Amount = Math.max(0, Number(position.otherCost1Amount) || 0)
  const otherCost2Amount = Math.max(0, Number(position.otherCost2Amount) || 0)
  const otherCostsTotal = otherCost1Amount + otherCost2Amount

  const totalCostPerPiece = materialCost + programmingCost + setupCost + machiningCost + inspectionCost + transportCost + surfaceProtectionCost + heatTreatmentCost + otherOperationCost + ensatCostPerPiece + otherCostsTotal
  const safeMarginPercent = Math.min(200, Math.max(0, effectiveMarginPercent))
  const marginAmount = totalCostPerPiece * (safeMarginPercent / 100)
  const sellingPricePerPiece = totalCostPerPiece + marginAmount
  const marginValuePerPiece = sellingPricePerPiece - totalCostPerPiece
  const totalSellingPrice = sellingPricePerPiece * quantity
  const totalCost = totalCostPerPiece * quantity

  return { volumeM3, crossSectionAreaMm2, geometryWarning, weightKg, outputWeightKg, materialCost, programmingCost, setupCost, machiningCost, inspectionCostSeries, inspectionCost, transportCost, surfaceProtectionCost, heatTreatmentCost, otherOperationCost, ensatCount, ensatCostPerPiece, otherCost1Amount, otherCost2Amount, otherCostsTotal, totalCostPerPiece, totalCost, machineRate: machine.machiningHourlyRate, machineProgrammingRate: machine.programmingHourlyRate, machineSetupRate: machine.setupHourlyRate, machineMachiningRate: machine.machiningHourlyRate, machineName: machine.name, marginAmount, sellingPricePerPiece, marginValuePerPiece, totalSellingPrice, safeMarginPercent }
}

function escapeExportHtml(value: unknown): string {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;")
}

function formatExportMoney(value: number): string {
  return new Intl.NumberFormat("de-DE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value) || 0)
}

function formatExportDate(value: unknown): string {
  if (!value) return "-"

  try {
    const date =
      typeof value === "object" && value !== null &&
      "toDate" in value && typeof (value as { toDate?: unknown }).toDate === "function"
        ? (value as { toDate: () => Date }).toDate()
        : new Date(value as string | number | Date)

    if (Number.isNaN(date.getTime())) return "-"

    return `${String(date.getDate()).padStart(2, "0")}.${String(
      date.getMonth() + 1,
    ).padStart(2, "0")}.${date.getFullYear()}`
  } catch {
    return "-"
  }
}


/**
 * Rüstkosten su ukupan trošak programiranja + pripreme za seriju;
 * Stückkosten su ostali troškovi po komadu uvećani za maržu.
 */
function exportQuotePositionsToExcel(quote: Quote): void {
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

  const rows = (quote.positions || []).flatMap((position: any, index) => {
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

/**
 * Pravi kopiju ponude za poseban PDF TG izvoz. Dodatna marža se dodaje
 * na postojeće prodajne cijene, bez mijenjanja originalnog objekta ponude.
 * Jedinične cijene zaokružujemo na cent, a ukupne vrijednosti računamo iz
 * tih zaokruženih cijena kako bi redovi u PDF-u bili međusobno usklađeni.
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

/** Popunjava unaprijed otvoren prozor za štampu kako blokada pop-up prozora ne bi spriječila PDF izvoz. */
function writeQuotePdf(printWindow: Window, quote: Quote, documentHeading = "PONUDA"): void {
  const hasStaffelPrices = Boolean(
    (quote as any).staffelpreiseAktivno ||
    (quote.positions || []).some((position: any) => Array.isArray(position.staffelpreise) && position.staffelpreise.length > 0),
  )

  const rows = (quote.positions || []).flatMap((position: any, index) => {
    const positionNumber = position.positionNumber || String(index + 1).padStart(3, "0")
    const tiers = Array.isArray(position.staffelpreise) ? position.staffelpreise : []

    if (hasStaffelPrices && tiers.length > 0) {
      return tiers.map((tier: any) => `
        <tr>
          <td>${escapeExportHtml(positionNumber)}</td>
          <td>${escapeExportHtml(position.code || "-")}</td>
          <td>${escapeExportHtml(position.name || "-")}</td>
          <td class="number">${escapeExportHtml(position.quantity ?? 0)}</td>
          <td class="number">${escapeExportHtml(tier.quantity ?? 0)}</td>
          <td class="money">${escapeExportHtml(formatExportMoney(Number(tier.sellingPricePerPiece) || 0))}</td>
          <td class="money">${escapeExportHtml(formatExportMoney(Number(tier.totalSellingPrice) || 0))}</td>
        </tr>
      `)
    }

    return [`
      <tr>
        <td>${escapeExportHtml(positionNumber)}</td>
        <td>${escapeExportHtml(position.code || "-")}</td>
        <td>${escapeExportHtml(position.name || "-")}</td>
        <td class="number">${escapeExportHtml(position.quantity ?? 0)}</td>
        <td class="number">-</td>
        <td class="money">${escapeExportHtml(formatExportMoney(Number(position.sellingPricePerPiece) || 0))}</td>
        <td class="money">${escapeExportHtml(formatExportMoney(Number(position.totalSellingPrice) || 0))}</td>
      </tr>
    `]
  }).join("")

  const html = `<!doctype html>
<html lang="bs">
<head>
  <meta charset="utf-8">
  <title>${escapeExportHtml(documentHeading)} ${escapeExportHtml(quote.brojPonude || "")}</title>
  <style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #172033; margin: 0; font-size: 10pt; }
    .topline { border-bottom: 3px solid #4f46e5; padding-bottom: 12px; margin-bottom: 20px; }
    h1 { font-size: 24pt; margin: 0 0 8px; letter-spacing: .5px; }
    .quote-number { color: #4f46e5; font-weight: 700; font-size: 12pt; }
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
    .totals { margin: 22px 0 0 auto; width: 300px; border-top: 2px solid #4f46e5; padding-top: 12px; }
    .total-row { display: flex; justify-content: space-between; gap: 20px; font-size: 13pt; font-weight: 700; }
    .footer { margin-top: 32px; color: #64748b; font-size: 8pt; border-top: 1px solid #cbd5e1; padding-top: 8px; }
    @media screen { body { padding: 20px; } }
  </style>
</head>
<body>
  <div class="topline">
    <h1>${escapeExportHtml(documentHeading)}</h1>
    <div class="quote-number">${escapeExportHtml(quote.brojPonude || "-")}</div>
  </div>
  <div class="meta">
    <div class="meta-block"><div class="label">Kupac</div><div class="value">${escapeExportHtml(quote.kupacNaziv || "-")}</div></div>
    ${String((quote as any).brojUpitaKupca || "").trim() ? `<div class="meta-block"><div class="label">Broj upita kupca</div><div class="value">${escapeExportHtml(String((quote as any).brojUpitaKupca).trim())}</div></div>` : ""}
    <div class="meta-block"><div class="label">Datum ponude</div><div class="value">${escapeExportHtml(formatExportDate(quote.datum))}</div></div>
    <div class="meta-block"><div class="label">Broj pozicija</div><div class="value">${(quote.positions || []).length}</div></div>
  </div>
  <table>
    <thead><tr><th>Br.</th><th>Šifra</th><th>Naziv</th><th>Required Qty</th><th>Price Scale Qty</th><th>Cijena / kom (€)</th><th>Ukupno za količinu (€)</th></tr></thead>
    <tbody>${rows || '<tr><td colspan="7">Ponuda nema pozicija.</td></tr>'}</tbody>
  </table>
  ${hasStaffelPrices
    ? '<div class="footer"><strong>Napomena:</strong> Navedene količine su alternativni cjenovni nivoi za isti artikal. Ukupne vrijednosti različitih količina se ne sabiraju.</div>'
    : `<div class="totals"><div class="total-row"><span>Ukupna vrijednost</span><span>${escapeExportHtml(formatExportMoney(Number(quote.totalSellingPrice) || 0))} €</span></div></div>`}
  <div class="footer">Cijene su izražene u EUR.</div>
  <script>window.addEventListener('load', function () { window.focus(); setTimeout(function () { window.print(); }, 250); });</script>
</body>
</html>`

  printWindow.document.open()
  printWindow.document.write(html)
  printWindow.document.close()
}

/**
 * Namjenski TG obrazac prema dostavljenom Trendy Germany Angebot.pdf.
 * Standardni PDF koristi writeQuotePdf i ostaje potpuno nepromijenjen.
 */
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
        <td class="ident">${escapeExportHtml(ident)}</td>
        <td class="description">${escapeExportHtml(name)}</td>
        <td class="quantity">${escapeExportHtml(priceRow.quantity.toLocaleString("de-DE"))}</td>
        <td class="unit">Stk</td>
        <td class="money">${escapeExportHtml(formatExportMoney(priceRow.price))} €</td>
        <td class="money">${escapeExportHtml(formatExportMoney(priceRow.total))} €</td>
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
  const quoteDate = formatExportDate(quote.datum)
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
  <title>Angebot ${escapeExportHtml(offerReference)}</title>
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
    .logo {
      display: block;
      width: 36mm;
      height: 33mm;
      object-fit: contain;
      object-position: left top;
    }
    .seller-address {
      min-width: 58mm;
      padding-top: 1mm;
      font-size: 9pt;
      line-height: 1.35;
      white-space: nowrap;
    }
    .recipient-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12mm;
      min-height: 30mm;
      margin-bottom: 7mm;
    }
    .recipient-box {
      width: 58mm;
      min-height: 30mm;
      padding: 1.5mm;
      border: 0.45mm solid #222;
      font-size: 9pt;
      line-height: 1.35;
      overflow-wrap: anywhere;
    }
    .recipient-box div { min-height: 4mm; }
    .offer-date {
      min-width: 55mm;
      padding-top: 1mm;
      text-align: center;
      font-size: 11pt;
      font-weight: 700;
      white-space: nowrap;
    }
    .offer-title {
      margin: 0 0 18mm 1mm;
      font-size: 19pt;
      line-height: 1.2;
      font-weight: 700;
      letter-spacing: -0.25pt;
    }
    .offer-title span { margin-left: 3mm; }
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin: 0;
    }
    thead { display: table-header-group; }
    tfoot { display: table-row-group; }
    tr { page-break-inside: avoid; break-inside: avoid; }
    th, td {
      border: 0.35mm solid #111;
      padding: 1.05mm 0.9mm;
      vertical-align: middle;
      font-size: 8.6pt;
      line-height: 1.15;
    }
    th {
      background: #c4c4c4;
      text-align: left;
      font-size: 8.8pt;
      font-weight: 700;
    }
    .col-ident { width: 17%; }
    .col-name { width: 41%; }
    .col-quantity { width: 9%; }
    .col-unit { width: 8%; }
    .col-price { width: 12%; }
    .col-total { width: 13%; }
    td.ident { overflow-wrap: anywhere; }
    td.description { overflow-wrap: anywhere; }
    td.quantity, th.quantity { text-align: right; white-space: nowrap; }
    td.unit, th.unit { text-align: center; white-space: nowrap; }
    td.money, th.money { text-align: right; white-space: nowrap; }
    .sum-row td {
      border-left: 0;
      border-right: 0;
      border-bottom: 0;
      border-top: 0;
      padding-top: 2mm;
      font-size: 10pt;
      font-weight: 700;
    }
    .sum-row .sum-label { text-align: right; }
    .sum-row .sum-value { text-align: right; white-space: nowrap; }
    .staffel-note {
      margin: 4mm 0 0;
      font-size: 8pt;
      line-height: 1.4;
    }
    .closing {
      margin-top: 22mm;
      font-size: 8.5pt;
      line-height: 1.4;
    }
    .closing p { margin: 0 0 5mm; }
    .signature { margin-top: 0; }
    @media screen {
      body { padding: 14mm; }
      .document { max-width: 190mm; margin: 0 auto; }
    }
  </style>
</head>
<body>
  <main class="document">
    <header class="letterhead">
      <img class="logo" src="${escapeExportHtml(logoUrl)}" alt="Trendy Germany">
      <div class="seller-address">
        <div>Trendy Germany GmbH</div>
        <div>Kaiserstrasse 150</div>
        <div>51643 Gummersbach</div>
        <div>Tel: +49 (0)2261 5909949</div>
        <div>e-mail: info@trendy-germany.de</div>
      </div>
    </header>

    <section class="recipient-row">
      <div class="recipient-box">
        ${customerLines.map((line) => `<div>${escapeExportHtml(line)}</div>`).join("")}
      </div>
      <div class="offer-date">Gummersbach, ${escapeExportHtml(quoteDate)}</div>
    </section>

    <h1 class="offer-title">Angebot:<span>${escapeExportHtml(offerReference)}</span></h1>

    <table aria-label="Angebotspositionen">
      <colgroup>
        <col class="col-ident"><col class="col-name"><col class="col-quantity">
        <col class="col-unit"><col class="col-price"><col class="col-total">
      </colgroup>
      <thead>
        <tr>
          <th>Ident</th>
          <th>Name</th>
          <th class="quantity">Menge</th>
          <th class="unit">Einheit</th>
          <th class="money">Preis</th>
          <th class="money">Total</th>
        </tr>
      </thead>
      <tbody>
        ${rows || '<tr><td colspan="6">Keine Positionen vorhanden.</td></tr>'}
      </tbody>
      ${hasStaffelPrices ? "" : `
        <tfoot>
          <tr class="sum-row">
            <td colspan="5" class="sum-label">Summe EUR</td>
            <td class="sum-value">${escapeExportHtml(formatExportMoney(totalSellingPrice))} €</td>
          </tr>
        </tfoot>
      `}
    </table>

    ${hasStaffelPrices ? '<p class="staffel-note">Hinweis: Die angegebenen Mengenstaffeln sind alternative Preisstufen für denselben Artikel. Die Gesamtbeträge der unterschiedlichen Mengen werden nicht addiert.</p>' : ""}

    <footer class="closing">
      <p>Bei Rückfragen stehen wir selbstverständlich jederzeit gerne zur Verfügung.</p>
      <p>Mit freundlichen Grüßen,</p>
      <p class="signature">Edvin Čakić.</p>
    </footer>
  </main>
  <script>
    window.addEventListener('load', function () {
      window.focus();
      setTimeout(function () { window.print(); }, 300);
    });
  </script>
</body>
</html>`

  printWindow.document.open()
  printWindow.document.write(html)
  printWindow.document.close()
}

function NewQuote() {
  const { profile: authProfile } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()
  const routeState = location.state as {
    historyPosition?: Record<string, any>
    historySource?: { quoteId?: string; quoteNumber?: string; customerName?: string; date?: string }
  } | null
  const historyPosition = routeState?.historyPosition
  const historySource = routeState?.historySource
  const [historyPrefillNotice, setHistoryPrefillNotice] = useState("")
  const excelInquiryInputRef = useRef<HTMLInputElement>(null)
  const pdfInquiryInputRef = useRef<HTMLInputElement>(null)
  const [importPreview, setImportPreview] = useState<InquiryImportRow[] | null>(null)
  const [importSourceName, setImportSourceName] = useState("")
  const [importError, setImportError] = useState("")
  const [importInfoMessage, setImportInfoMessage] = useState("")
  const [customerRecognitionMessage, setCustomerRecognitionMessage] = useState("")
  const [customerRecognitionSuggestions, setCustomerRecognitionSuggestions] = useState<Customer[]>([])
  const [savingImportProfile, setSavingImportProfile] = useState(false)
  const [importingInquiry, setImportingInquiry] = useState(false)
  const [checkingImportPositions, setCheckingImportPositions] = useState(false)
  const [importPositionCheckUsed, setImportPositionCheckUsed] = useState(false)
  const [importPositionChecks, setImportPositionChecks] = useState<Record<number, ImportPositionDatabaseCheck>>({})
  const [pdfImportConfirmed, setPdfImportConfirmed] = useState(false)
  const [excelImportGrid, setExcelImportGrid] = useState<string[][] | null>(null)
  const [showExcelColumnMapping, setShowExcelColumnMapping] = useState(false)
  const [excelDataStartRow, setExcelDataStartRow] = useState(0)
  const [excelColumnMapping, setExcelColumnMapping] = useState<ExcelColumnMapping>({
    positionNumber: 0,
    code: 1,
    name: 2,
    quantity: 3,
    priceScale: -1,
  })

  const importValidation = useMemo(() => {
    const rows = importPreview ?? []
    const counts = new Map<string, number>()
    for (const row of rows) {
      const normalizedCode = row.code.trim().toLocaleUpperCase().replace(/\s+/g, "")
      if (normalizedCode) counts.set(normalizedCode, (counts.get(normalizedCode) ?? 0) + 1)
    }

    const rowResults = rows.map((row) => {
      const issues: string[] = []
      const code = row.code.trim().toLocaleUpperCase().replace(/\s+/g, "")
      const quantityIsValid = Number.isFinite(Number(row.quantity)) && Number(row.quantity) > 0
      const scaleText = String(row.staffelQuantityText || "").trim()
      const scaleIsInvalid = Boolean(scaleText && hasInvalidStaffelQuantity(scaleText))
      const scaleIsTooLong = parseStaffelQuantities(scaleText).length > 30
      if (!row.positionNumber.trim()) issues.push("Nedostaje broj pozicije")
      if (!code) issues.push("Nedostaje šifra")
      if (!row.name.trim()) issues.push("Nedostaje naziv")
      if (!quantityIsValid) issues.push("Neispravna količina")
      if (scaleIsInvalid) issues.push("Neispravna Price Scale količina")
      if (scaleIsTooLong) issues.push("Previše Price Scale količina (maks. 30)")
      if (code && (counts.get(code) ?? 0) > 1) issues.push("Ponavlja se šifra")
      return {
        issues,
        blocking: !code || !quantityIsValid || scaleIsInvalid || scaleIsTooLong,
        missingName: !row.name.trim(),
        duplicateCode: Boolean(code && (counts.get(code) ?? 0) > 1),
      }
    })

    return {
      rows: rowResults,
      missingCodeCount: rowResults.filter((row) => row.issues.includes("Nedostaje šifra")).length,
      invalidQuantityCount: rowResults.filter((row) => row.issues.includes("Neispravna količina")).length,
      missingNameCount: rowResults.filter((row) => row.missingName).length,
      duplicateCodeCount: rowResults.filter((row) => row.duplicateCode).length,
      blockingCount: rowResults.filter((row) => row.blocking).length,
    }
  }, [importPreview])

  const importPositionCheckSummary = useMemo(() => {
    const checks = Object.values(importPositionChecks)
    return {
      checked: checks.length,
      found: checks.filter((check) => check.status === "found").length,
      newPositions: checks.filter((check) => check.status === "new").length,
      nameMismatches: checks.filter((check) => check.nameMismatch).length,
      errors: checks.filter((check) => check.status === "error").length,
      missingCodes: checks.filter((check) => check.status === "missing-code").length,
    }
  }, [importPositionChecks])

  const excelColumnCount = useMemo(
    () => Math.max(0, ...(excelImportGrid ?? []).map((row) => row.length)),
    [excelImportGrid],
  )

  const [
    positions,
    setPositions,
  ] = useState<Position[]>([
    createEmptyPosition("001"),
  ])

  const [
    selectedIndex,
    setSelectedIndex,
  ] = useState(0)

  // Nakon tastaturne navigacije između pozicija fokusiramo prvo ili posljednje polje.
  const focusAfterPositionChangeRef = useRef<"first" | "last" | null>(null)

  useEffect(() => {
    const focusDirection = focusAfterPositionChangeRef.current
    if (!focusDirection) return
    focusAfterPositionChangeRef.current = null

    requestAnimationFrame(() => {
      const formGrid = document.querySelector<HTMLElement>(".form-grid")
      if (!formGrid) return
      const fields = Array.from(formGrid.querySelectorAll<HTMLElement>(
        'input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), select, textarea',
      )).filter((field) => !field.hasAttribute("disabled") && field.getClientRects().length > 0)
      const target = focusDirection === "first" ? fields[0] : fields[fields.length - 1]
      target?.focus()
    })
  }, [selectedIndex])

  // Kopiranje tehničkih i kalkulacijskih ulaza između pozicija iste ponude.
  const [copyPositionSourceIndex, setCopyPositionSourceIndex] = useState<number | null>(null)

  // Staffelpreise su opcionalne količinske cijene za svaku poziciju u ponudi.
  // Količine su zajedničke za sve pozicije; cijena se računa zasebno po količini.
  const [staffelPricesEnabled, setStaffelPricesEnabled] = useState(false)
  const [staffelMode, setStaffelMode] = useState<StaffelMode>("shared")
  const [staffelQuantityText, setStaffelQuantityText] = useState(DEFAULT_STAFFEL_QUANTITIES)
  const staffelQuantities = useMemo(
    () => parseStaffelQuantities(staffelQuantityText),
    [staffelQuantityText],
  )
  const staffelQuantityInputInvalid = hasInvalidStaffelQuantity(staffelQuantityText)

  const changeStaffelMode = (nextMode: StaffelMode) => {
    if (nextMode === "per-position" && staffelMode !== "per-position") {
      // Pri prelasku sa zajedničkih na pojedinačne količine inicijalno kopiramo
      // zajedničku listu samo u prazne pozicije; postojeći importovani Price Scale ostaje.
      setPositions((current) => current.map((item) => ({
        ...item,
        staffelQuantityText: item.staffelQuantityText?.trim()
          ? item.staffelQuantityText
          : staffelQuantityText,
      })))
    }
    setStaffelMode(nextMode)
  }

  // Status pretrage šifre prikazujemo uz aktivnu poziciju,
  // kako bi kalkulant odmah znao je li ranija kalkulacija pronađena.
  const [positionLookupStatuses, setPositionLookupStatuses] = useState<
    Record<string, PositionLookupStatus>
  >({})
  // Zadržavamo rezultate poređenja iz prozora uvoza i nakon potvrde uvoza,
  // kako bi status i preporučena akcija ostali vidljivi u glavnoj tabeli.
  const [positionImportReviewByCode, setPositionImportReviewByCode] = useState<
    Record<string, ImportedPositionReview>
  >({})
  // Šifre iz kojih je korisnik izričito odabrao da NE preuzme tehničke
  // podatke iz baze ne smiju kasnije automatski prepisati postojeći zapis.
  const [skipPositionDatabaseSyncCodes, setSkipPositionDatabaseSyncCodes] = useState<Record<string, boolean>>({})

  const setPositionLookupStatus = (
    code: string,
    status: PositionLookupStatus,
  ) => {
    const key = normalizePositionLookupCode(code)
    if (!key) return

    setPositionLookupStatuses((current) => ({
      ...current,
      [key]: status,
    }))
  }

  const clearPositionLookupStatuses = (...codes: string[]) => {
    setPositionLookupStatuses((current) => {
      const next = { ...current }
      for (const code of codes) {
        const key = normalizePositionLookupCode(code)
        if (key) delete next[key]
      }
      return next
    })
    setPositionImportReviewByCode((current) => {
      const next = { ...current }
      for (const code of codes) {
        const key = normalizePositionLookupCode(code)
        if (key) delete next[key]
      }
      return next
    })
  }

  const [
  customers,
  setCustomers,
] = useState<Customer[]>([])

const [
  selectedCustomerId,
  setSelectedCustomerId,
] = useState("")

// Opcionalni referentni broj kupčevog upita; čuva se kao tekst da se sačuvaju vodeće nule.
const [customerInquiryNumber, setCustomerInquiryNumber] = useState("")

const [
  customersLoading,
  setCustomersLoading,
] = useState(true)

  /* ==========================================
   * UREĐIVANJE POSTOJEĆE PONUDE
   * ========================================== */

  const quoteSearchParams = new URLSearchParams(window.location.search)
  const editQuoteId = quoteSearchParams.get("edit")
  const copyQuoteId = quoteSearchParams.get("copy")
  const sourceQuoteId = editQuoteId || copyQuoteId

  const isEditing = Boolean(editQuoteId)
  const quoteDraftId = quoteSearchParams.get("new") || ""
  const currentUserUid = authProfile?.uid || ""

  const [draftHydrated, setDraftHydrated] = useState(false)
  const [draftSelectionResolved, setDraftSelectionResolved] = useState(false)
  const [showDraftRecovery, setShowDraftRecovery] = useState(false)
  const [draftRecoveryItems, setDraftRecoveryItems] = useState<QuoteDraftRecord[]>([])
  const [draftSyncStatus, setDraftSyncStatus] = useState<"loading" | "idle" | "local" | "saving" | "saved" | "error">("loading")
  const [draftSyncMessage, setDraftSyncMessage] = useState("")
  const draftHasStartedRef = useRef(false)
  const draftCreatedAtMsRef = useRef(Date.now())
  const draftWriteTimerRef = useRef<number | null>(null)
  const draftWriteQueueRef = useRef<Promise<void>>(Promise.resolve())
  const latestDraftEnvelopeRef = useRef<{
    label: string
    positionCount: number
    createdAtMs: number
    updatedAtMs: number
    state: Record<string, unknown>
  } | null>(null)
  const finalizedDraftSignatureRef = useRef<string | null>(null)
  const localDraftStorageKey = currentUserUid && quoteDraftId
    ? `cnc_quote_draft_v1_${currentUserUid}_${quoteDraftId}`
    : ""

  // Pamti ID ponude odmah nakon prvog spremanja, kako naredni klikovi
  // na Sačuvaj / Izvezi ažuriraju istu ponudu umjesto da prave duplikate.
  const [savedQuoteId, setSavedQuoteId] = useState<string | null>(editQuoteId)
  const [exportingFormat, setExportingFormat] = useState<"pdf" | "excel" | null>(null)
  const [showTgExportModal, setShowTgExportModal] = useState(false)
  const [tgMarkupPercentText, setTgMarkupPercentText] = useState("")
  const [tgExportError, setTgExportError] = useState("")


  // Tajmer se pokreće samo za novu ponudu (uključujući kopiju postojeće ponude),
  // a ne pri otvaranju već spremljene ponude radi uređivanja.
  const isNewQuoteTimerSessionRef = useRef(!editQuoteId)
  const quotePreparationStartedAtRef = useRef<Date | null>(null)
  const quotePreparationStartedAtMsRef = useRef<number | null>(null)
  const quotePreparationTimerFinalizedRef = useRef(false)
  const [quotePreparationElapsedMs, setQuotePreparationElapsedMs] = useState(0)
  const [quotePreparationTimerFinished, setQuotePreparationTimerFinished] = useState(false)

  useEffect(() => {
    if (!isNewQuoteTimerSessionRef.current) {
      // Stari, nedovršeni tajmer ne smije se prenijeti na uređivanje stare ponude.
      try {
        window.sessionStorage.removeItem(QUOTE_PREPARATION_TIMER_STORAGE_KEY)
      } catch {
        // Tajmer radi i bez sessionStorage podrške.
      }
      return
    }

    let startedAtMs = Date.now()
    try {
      // Reset only once for each explicit "new" navigation. The query token
      // remains in the URL on refresh, so remembering it prevents the timer
      // from restarting every time the browser reloads the page.
      const newQuoteToken = new URLSearchParams(window.location.search).get("new")
      if (
        newQuoteToken &&
        window.sessionStorage.getItem(QUOTE_PREPARATION_LAST_NEW_TOKEN_STORAGE_KEY) !== newQuoteToken
      ) {
        window.sessionStorage.removeItem(QUOTE_PREPARATION_TIMER_STORAGE_KEY)
        window.sessionStorage.setItem(QUOTE_PREPARATION_LAST_NEW_TOKEN_STORAGE_KEY, newQuoteToken)
      }

      const storedStart = Number(
        window.sessionStorage.getItem(QUOTE_PREPARATION_TIMER_STORAGE_KEY),
      )
      if (Number.isFinite(storedStart) && storedStart > 0 && storedStart <= startedAtMs) {
        // Ako se stranica osvježi tokom izrade, nastavljamo mjeriti od prvog otvaranja.
        startedAtMs = storedStart
      } else {
        window.sessionStorage.setItem(
          QUOTE_PREPARATION_TIMER_STORAGE_KEY,
          String(startedAtMs),
        )
      }
    } catch {
      // Ograničeni preglednici mogu blokirati sessionStorage; koristimo vrijeme trenutne sesije.
    }

    quotePreparationStartedAtMsRef.current = startedAtMs
    quotePreparationStartedAtRef.current = new Date(startedAtMs)
    setQuotePreparationElapsedMs(Math.max(0, Date.now() - startedAtMs))

    const intervalId = window.setInterval(() => {
      if (quotePreparationTimerFinalizedRef.current) return
      const startMs = quotePreparationStartedAtMsRef.current
      if (startMs !== null) {
        setQuotePreparationElapsedMs(Math.max(0, Date.now() - startMs))
      }
    }, 1000)

    return () => window.clearInterval(intervalId)
  }, [])

  const [
    editingQuote,
    setEditingQuote,
  ] = useState<Quote | null>(null)

  const [
    editingQuoteLoading,
    setEditingQuoteLoading,
  ] = useState(false)
  const [quoteSourceLoaded, setQuoteSourceLoaded] = useState(!sourceQuoteId)

  const position =
    positions[selectedIndex]

  const currentPositionLookupStatus = position.code
    ? positionLookupStatuses[normalizePositionLookupCode(position.code)]
    : undefined

  // Historija ponuda se učitava samo za prikaz informativne prethodne cijene.
  // Ta cijena se nikad automatski ne koristi u aktuelnoj kalkulaciji.
  const [quoteHistory, setQuoteHistory] = useState<Quote[]>([])
  const [quoteHistoryLoading, setQuoteHistoryLoading] = useState(true)
  const [quoteHistoryError, setQuoteHistoryError] = useState(false)

  const previousQuotePrices = useMemo<PreviousQuotePrice[]>(() => {
    const normalizedCode = normalizePositionLookupCode(position.code || "")
    if (!normalizedCode) return []

    const activeQuoteId = savedQuoteId || editQuoteId
    const matches: PreviousQuotePrice[] = []
    const seenQuotes = new Set<string>()

    for (const quote of quoteHistory) {
      // Pri uređivanju ponude ne prikazujemo cijenu iz iste ponude kao historijsku.
      if (activeQuoteId && quote.id === activeQuoteId) continue

      const savedPositions = Array.isArray(quote.positions)
        ? quote.positions
        : []

      for (const savedPosition of savedPositions as any[]) {
        if (
          normalizePositionLookupCode(String(savedPosition.code || "")) !==
          normalizedCode
        ) {
          continue
        }

        const pricePerPiece = Number(savedPosition.sellingPricePerPiece)
        if (!Number.isFinite(pricePerPiece) || pricePerPiece <= 0) continue

        const dateValue = quote.datum ?? quote.createdAt ?? quote.updatedAt ?? null
        const quoteKey = String(
          quote.id || `${quote.brojPonude || ""}-${getDateMillis(dateValue)}`,
        )

        // U historiji prikazujemo najviše jedan zapis po ponudi,
        // čak i ako je ista šifra greškom navedena više puta.
        if (seenQuotes.has(quoteKey)) continue
        seenQuotes.add(quoteKey)

        matches.push({
          quoteId: quote.id,
          quoteNumber: String(quote.brojPonude || "Bez broja"),
          dateValue,
          dateMillis: getDateMillis(dateValue),
          customerName: String(quote.kupacNaziv || "Nepoznat kupac"),
          quantity: Number(savedPosition.quantity) || 1,
          pricePerPiece,
        })
        break
      }
    }

    matches.sort((a, b) => b.dateMillis - a.dateMillis)
    return matches
  }, [quoteHistory, position.code, savedQuoteId, editQuoteId])

  const previousQuotePrice = previousQuotePrices[0] || null

  useEffect(() => {
    let isMounted = true

    async function loadQuoteHistory() {
      try {
        setQuoteHistoryLoading(true)
        setQuoteHistoryError(false)
        const history = await getAllQuotes()
        if (isMounted) setQuoteHistory(history)
      } catch (error) {
        console.error("Greška pri učitavanju historije cijena:", error)
        if (isMounted) setQuoteHistoryError(true)
      } finally {
        if (isMounted) setQuoteHistoryLoading(false)
      }
    }

    loadQuoteHistory()
    return () => {
      isMounted = false
    }
  }, [])

  /*
   * ==========================================
   * MARŽA
   * ==========================================
   *
   * Default vrijednost je 20%.
   *
   * Marža se ovdje računa kao uvećanje troška:
   *
   * Prodajna cijena =
   * Trošak × (1 + Marža / 100)
   *
   * Primjer:
   * 100% = trošak × 2
   * 200% = trošak × 3
   *
   */

  const [
    marginPercent,
    setMarginPercent,
  ] = useState(20)

  // Naslijeđene zadane vrijednosti služe samo za kompatibilnost sa starim
  // ponudama i pozicijama koje još nemaju vlastite postavke.
  const [transportIncluded, setTransportIncluded] = useState(true)

  // Simulacijska cijena služi samo za analizu profitabilnosti;
  // nikada direktno ne mijenja cijenu koja se sprema u ponudu.
  const [scenarioSellingPrices, setScenarioSellingPrices] = useState<
    Record<string, string>
  >({})

  /*
   * ==========================================
   * POVRŠINSKE ZAŠTITE
   * ==========================================
   */

  const [
    surfaceProtections,
    setSurfaceProtections,
  ] = useState<SurfaceProtection[]>([])

  /*
   * ==========================================
   * TERMIČKE OBRADE
   * ==========================================
   */

  const [
    heatTreatments,
    setHeatTreatments,
  ] = useState<HeatTreatment[]>([])

  const [
    otherOperations,
    setOtherOperations,
  ] = useState<OtherOperation[]>([])

  const [
    treatmentsLoading,
    setTreatmentsLoading,
  ] = useState(true)

  const [
    generalSettings,
    setGeneralSettings,
  ] = useState<GeneralSettings>({
    transportPricePerKg: 0,
    ensatPricePerPiece: 0,
  })

  // Materijali se učitavaju iz Firestorea; stare vrijednosti služe samo
  // kao privremena kompatibilnost dok se ne popuni nova baza materijala.
  const [databaseMaterials, setDatabaseMaterials] = useState<MaterialRecord[]>([])
  const [materialsLoading, setMaterialsLoading] = useState(true)
  const [materialsLoadError, setMaterialsLoadError] = useState(false)

  // Mašine i satnice dolaze iz Firestorea. Stare satnice služe kao
  // privremeni fallback dok se početni zapisi ne unesu u novu bazu.
  const [databaseMachines, setDatabaseMachines] = useState<MachineRecord[]>([])
  const [machinesLoading, setMachinesLoading] = useState(true)
  const [machinesLoadError, setMachinesLoadError] = useState(false)

  useEffect(() => {
    let isMounted = true

    async function loadMachinesFromDatabase() {
      try {
        setMachinesLoading(true)
        setMachinesLoadError(false)
        const items = await getMachines()
        if (isMounted) setDatabaseMachines(items)
      } catch (error) {
        console.error("Greška pri učitavanju baze mašina:", error)
        if (isMounted) setMachinesLoadError(true)
      } finally {
        if (isMounted) setMachinesLoading(false)
      }
    }

    void loadMachinesFromDatabase()
    return () => {
      isMounted = false
    }
  }, [])

  const machines = useMemo<Record<string, MachineRate>>(() => {
    const result: Record<string, MachineRate> = { ...defaultMachines }
    for (const item of databaseMachines) {
      const code = String(item.oznaka ?? "").trim()
      if (!code) continue
      result[code] = {
        name: String(item.naziv || code),
        programmingHourlyRate: Number.isFinite(Number(item.satnicaProgramiranja))
          ? Math.max(0, Number(item.satnicaProgramiranja))
          : (defaultMachines[code]?.programmingHourlyRate ?? 35),
        setupHourlyRate: Number.isFinite(Number(item.satnicaPripreme))
          ? Math.max(0, Number(item.satnicaPripreme))
          : (defaultMachines[code]?.setupHourlyRate ?? 0),
        machiningHourlyRate: Number.isFinite(Number(item.satnicaObrade))
          ? Math.max(0, Number(item.satnicaObrade))
          : (defaultMachines[code]?.machiningHourlyRate ?? 0),
      }
    }
    return result
  }, [databaseMachines])

  const machineOptions = useMemo(() => {
    const options: Array<{ code: string; label: string; active: boolean }> =
      databaseMachines.length === 0
        ? Object.entries(defaultMachines).map(([code, item]) => ({
            code,
            label: item.name,
            active: true,
          }))
        : databaseMachines
            .filter((item) => item.aktivna !== false)
            .map((item) => ({
              code: item.oznaka,
              label: item.naziv && item.naziv !== item.oznaka
                ? `${item.naziv} (${item.oznaka})`
                : item.naziv,
              active: true,
            }))

    // Postojeća ponuda može koristiti mašinu koja je kasnije deaktivirana
    // ili još nije prenesena u bazu. Prikazujemo je, ali je označimo kao
    // neaktivnu/izvan baze umjesto da izgubimo odabranu vrijednost.
    if (position.machine && !options.some((item) => item.code === position.machine)) {
      const databaseEntry = databaseMachines.find((item) => item.oznaka === position.machine)
      const fallbackEntry = machines[position.machine]
      options.unshift({
        code: position.machine,
        label: databaseEntry
          ? `${databaseEntry.naziv} (${databaseEntry.oznaka}) — neaktivna`
          : fallbackEntry
            ? `${fallbackEntry.name} — nije u bazi`
            : `${position.machine} — nije u bazi`,
        active: false,
      })
    }

    return options
  }, [databaseMachines, position.machine, machines])

  useEffect(() => {
    let isMounted = true

    async function loadMaterialsFromDatabase() {
      try {
        setMaterialsLoading(true)
        setMaterialsLoadError(false)
        const items = await getMaterials()
        if (isMounted) setDatabaseMaterials(items)
      } catch (error) {
        console.error("Greška pri učitavanju baze materijala:", error)
        if (isMounted) setMaterialsLoadError(true)
      } finally {
        if (isMounted) setMaterialsLoading(false)
      }
    }

    void loadMaterialsFromDatabase()
    return () => {
      isMounted = false
    }
  }, [])

  const materials = useMemo<Record<string, Material>>(() => {
    const result: Record<string, Material> = { ...defaultMaterials }
    for (const item of databaseMaterials) {
      const code = String(item.oznaka ?? "").trim()
      if (!code) continue
      result[code] = {
        name: String(item.naziv || code),
        density: Number(item.gustina) > 0
          ? Number(item.gustina)
          : (defaultMaterials[code]?.density ?? 7850),
        pricePerKg: Number.isFinite(Number(item.cijenaPoKg))
          ? Math.max(0, Number(item.cijenaPoKg))
          : (defaultMaterials[code]?.pricePerKg ?? 0),
      }
    }
    return result
  }, [databaseMaterials])

  const materialOptions = useMemo(() => {
    if (databaseMaterials.length === 0) {
      return Object.entries(defaultMaterials).map(([code, item]) => ({
        code,
        label: item.name,
        active: true,
      }))
    }

    const options: Array<{ code: string; label: string; active: boolean }> = databaseMaterials
      .filter((item) => item.aktivno !== false)
      .map((item) => ({
        code: item.oznaka,
        label: item.naziv && item.naziv !== item.oznaka
          ? `${item.oznaka} — ${item.naziv}`
          : item.oznaka,
        active: true,
      }))

    // Ako se uređuje stara ponuda s deaktiviranim ili ranije neunesenim
    // materijalom, ostavimo prikaz trenutno odabrane vrijednosti, ali je
    // jasno označimo kao neaktivnu / izvan baze.
    if (position.material && !options.some((item) => item.code === position.material)) {
      const record = databaseMaterials.find((item) => item.oznaka === position.material)
      options.unshift({
        code: position.material,
        label: record
          ? `${record.oznaka} — ${record.naziv} (neaktivan)`
          : `${position.material} (nije u bazi materijala)`,
        active: false,
      })
    }

    return options
  }, [databaseMaterials, position.material])

  const selectedMaterialDetails =
    materials[position.material] || defaultMaterials.S235

  /*
   * ==========================================
   * UČITAVANJE ZAŠTITA I OBRADA
   * ==========================================
   */

  useEffect(() => {
    async function loadTreatments() {
      try {
        setTreatmentsLoading(true)

        const [
          surfaceProtectionData,
          heatTreatmentData,
          otherOperationData,
        ] = await Promise.all([
          getSurfaceProtections(),
          getHeatTreatments(),
          getOtherOperations(),
        ])

        setSurfaceProtections(
          surfaceProtectionData.filter(
            (item) =>
              item.aktivno !== false,
          ),
        )

        setHeatTreatments(
          heatTreatmentData.filter(
            (item) =>
              item.aktivno !== false,
          ),
        )

        setOtherOperations(
          otherOperationData.filter(
            (item) => item.aktivno !== false,
          ),
        )
      } catch (error) {
        console.error(
          "Greška pri učitavanju zaštita i obrada:",
          error,
        )

        alert(
          "Greška pri učitavanju površinskih zaštita, termičkih obrada i ostalih zahvata.",
        )
      } finally {
        setTreatmentsLoading(false)
      }
    }

    loadTreatments()
  }, [])

  useEffect(() => {
  async function loadCustomers() {
    try {
      setCustomersLoading(true)

      const data = await getCustomers()

      setCustomers(
        data.filter(
          (customer) =>
            customer.aktivan !== false,
        ),
      )
    } catch (error) {
      console.error(
        "Greška pri učitavanju kupaca:",
        error,
      )

      alert(
        "Greška pri učitavanju kupaca.",
      )
    } finally {
      setCustomersLoading(false)
    }
  }

  loadCustomers()
}, [])

  /* ==========================================
   * UČITAVANJE PONUDE ZA UREĐIVANJE
   * ========================================== */

  useEffect(() => {
    if (!sourceQuoteId) {
      setQuoteSourceLoaded(true)
      return
    }

    let isCurrentLoad = true
    async function loadQuoteAsSource() {
      try {
        setQuoteSourceLoaded(false)
        setEditingQuoteLoading(true)

        const quote =
          await getQuoteById(sourceQuoteId!)

        // React StrictMode može pokrenuti paralelni probni effect; ne dozvoli
        // da zastarjeli odgovor prepiše novije učitavanje ili vraćenu radnu ponudu.
        if (!isCurrentLoad) return

        if (!quote) {
          alert("Ponuda za učitavanje nije pronađena.")
          return
        }

        // U režimu uređivanja čuvamo original kao aktivnu ponudu.
        // U režimu kopiranja preuzimamo samo podatke; original se ne mijenja.
        setEditingQuote(isEditing ? quote : null)

        setMarginPercent(
          Number(quote.marginPercent) || 20,
        )
        setTransportIncluded(quote.transportIncluded !== false)

        const savedStaffelQuantities = Array.isArray((quote as any).staffelKolicine)
          ? parseStaffelQuantities((quote as any).staffelKolicine.join(","))
          : []
        const hasSavedStaffelPrices = Boolean(
          (quote as any).staffelpreiseAktivno || savedStaffelQuantities.length > 0,
        )
        const savedStaffelMode: StaffelMode = (quote as any).staffelNacin === "per-position"
          ? "per-position"
          : (quote.positions || []).some((saved: any) => Array.isArray(saved.staffelKolicine) && saved.staffelKolicine.length > 0) && savedStaffelQuantities.length === 0
            ? "per-position"
            : "shared"
        setStaffelPricesEnabled(hasSavedStaffelPrices || (quote.positions || []).some((saved: any) =>
          (Array.isArray(saved.staffelpreise) && saved.staffelpreise.length > 0) ||
          (Array.isArray(saved.staffelKolicine) && saved.staffelKolicine.length > 0)
        ))
        setStaffelMode(savedStaffelMode)
        setStaffelQuantityText(
          savedStaffelQuantities.length > 0
            ? savedStaffelQuantities.join(", ")
            : DEFAULT_STAFFEL_QUANTITIES,
        )

        setSelectedCustomerId(
          quote.kupacId || "",
        )
        setCustomerInquiryNumber(String((quote as any).brojUpitaKupca || ""))

        const loadedPositions: Position[] =
          (quote.positions || []).map(
            (saved: any, index: number) => ({
              positionNumber:
                saved.positionNumber ||
                String(index + 1).padStart(3, "0"),

              code: saved.code || "",
              name: saved.name || "",
              quantity:
                Number(saved.quantity) || 1,
              marginPercent: Number.isFinite(Number(saved.marginPercent ?? saved.pricingSnapshot?.marginPercent))
                ? Number(saved.marginPercent ?? saved.pricingSnapshot?.marginPercent)
                : (Number(quote.marginPercent) || 20),
              transportIncluded: typeof saved.transportIncluded === "boolean"
                ? saved.transportIncluded
                : typeof saved.pricingSnapshot?.transportIncluded === "boolean"
                  ? saved.pricingSnapshot.transportIncluded
                  : quote.transportIncluded !== false,
              transportPricePerKg: Number.isFinite(Number(saved.transportPricePerKg ?? saved.pricingSnapshot?.transportPricePerKg ?? (quote as any).calculationSnapshot?.transportPricePerKg))
                ? Number(saved.transportPricePerKg ?? saved.pricingSnapshot?.transportPricePerKg ?? (quote as any).calculationSnapshot?.transportPricePerKg)
                : undefined,
              staffelQuantityText: Array.isArray(saved.staffelKolicine)
                ? saved.staffelKolicine.join(", ")
                : Array.isArray(saved.staffelpreise)
                  ? saved.staffelpreise.map((tier: any) => Number(tier.quantity)).filter((value: number) => Number.isInteger(value) && value > 0).join(", ")
                  : "",

              shape:
                saved.shape || "rectangular",

              length:
                Number(saved.length) || 0,
              width:
                Number(saved.width) || 0,
              height:
                Number(saved.height) || 0,
              diameter:
                Number(saved.diameter) || 0,
              rawWeightKg:
                Number(saved.rawWeightKg ?? saved.ulaznaTezinaKg) || 0,
              rawWeightInput:
                Number(saved.rawWeightKg ?? saved.ulaznaTezinaKg) > 0
                  ? String(Number(saved.rawWeightKg ?? saved.ulaznaTezinaKg))
                  : "",
              outputWeightKg:
                Number(saved.outputWeightKg) || 0,
              outputWeightInput:
                Number(saved.outputWeightKg) > 0 ? String(Number(saved.outputWeightKg)) : "",
              thickness:
                Number(saved.thickness ?? saved.debljina) || 0,
              flangeThickness:
                Number(saved.flangeThickness ?? saved.debljinaPrirubnice) || 0,
              webThickness:
                Number(saved.webThickness ?? saved.debljinaRebra) || 0,
              customCrossSectionArea:
                Number(saved.customCrossSectionArea ?? saved.povrsinaPresjekaMm2) || 0,

              material:
                saved.material || "S235",

              programmingMinutes:
                Number(saved.programmingMinutes) || 0,
              setupMinutes:
                Number(saved.setupMinutes) || 0,
              machiningMinutes:
                Number(saved.machiningMinutes) || 0,

              machine:
                saved.machine || "Haas_UMC500",

              machineProgrammingRate:
                isEditing
                  ? Number(saved.machineProgrammingRate ?? saved.programmingHourlyRate ?? saved.pricingSnapshot?.programmingHourlyRate ?? 35)
                  : undefined,

              machineSetupRate:
                isEditing && (saved.machineSetupRate !== undefined || saved.machineRate !== undefined)
                  ? Number(saved.machineSetupRate ?? saved.machineRate)
                  : undefined,

              machineMachiningRate:
                isEditing && (saved.machineMachiningRate !== undefined || saved.machineRate !== undefined)
                  ? Number(saved.machineMachiningRate ?? saved.machineRate)
                  : undefined,

              surfaceProtectionId:
                saved.surfaceProtectionId ||
                saved.surfaceProtection?.id ||
                "",

              surfaceProtectionName:
                saved.surfaceProtectionName ||
                saved.surfaceProtection?.naziv ||
                "",

              heatTreatmentId:
                saved.heatTreatmentId ||
                saved.heatTreatment?.id ||
                "",

              heatTreatmentName:
                saved.heatTreatmentName ||
                saved.heatTreatment?.naziv ||
                "",

              otherOperationId:
                saved.otherOperationId || saved.otherOperation?.id || "",
              otherOperationName:
                saved.otherOperationName || saved.otherOperation?.naziv || "",
              otherCost1Description:
                saved.otherCost1Description || "",
              otherCost1Amount:
                Number(saved.otherCost1Amount) || 0,
              otherCost2Description:
                saved.otherCost2Description || "",
              otherCost2Amount:
                Number(saved.otherCost2Amount) || 0,
              ensatCount:
                Number(saved.ensatCount) || 0,

              inspectionCostSeries:
                Number(saved.inspectionCostSeries) || 0,
            }),
          )

        if (loadedPositions.length > 0) {
          setPositions(loadedPositions)
          setSelectedIndex(0)
        }
      } catch (error) {
        if (isCurrentLoad) {
          console.error(
            "Greška pri učitavanju ponude za uređivanje:",
            error,
          )

          alert(
            "Greška pri učitavanju ponude.",
          )
        }
      } finally {
        if (isCurrentLoad) {
          setEditingQuoteLoading(false)
          setQuoteSourceLoaded(true)
        }
      }
    }

    void loadQuoteAsSource()
    return () => {
      isCurrentLoad = false
    }
  }, [sourceQuoteId, isEditing])

  // ==========================================
  // RADNE PONUDE – AUTOMATSKO ČUVANJE
  // ==========================================
  const buildDraftState = () => ({
    version: 1,
    positions,
    selectedIndex,
    selectedCustomerId,
    customerInquiryNumber,
    marginPercent,
    transportIncluded,
    staffelPricesEnabled,
    staffelMode,
    staffelQuantityText,
    positionLookupStatuses,
    positionImportReviewByCode,
    skipPositionDatabaseSyncCodes,
    historyPrefillNotice,
    importPreview,
    importSourceName,
    pdfImportConfirmed,
    excelImportGrid,
    showExcelColumnMapping,
    excelDataStartRow,
    excelColumnMapping,
    importPositionChecks,
    importPositionCheckUsed,
    scenarioSellingPrices,
    quotePreparationStartedAtMs: quotePreparationStartedAtMsRef.current,
  })

  const isDraftMeaningful = () => {
    if (
      selectedCustomerId ||
      customerInquiryNumber.trim() ||
      staffelPricesEnabled ||
      (importPreview?.length ?? 0) > 0 ||
      (excelImportGrid?.length ?? 0) > 0
    ) return true

    return positions.some((item) => Boolean(
      item.code.trim() ||
      item.name.trim() ||
      Number(item.length) > 0 ||
      Number(item.width) > 0 ||
      Number(item.height) > 0 ||
      Number(item.diameter) > 0 ||
      String(item.rawWeightInput || "").trim() ||
      Number(item.rawWeightKg) > 0 ||
      String(item.outputWeightInput || "").trim() ||
      Number(item.outputWeightKg) > 0 ||
      Number(item.thickness) > 0 ||
      Number(item.flangeThickness) > 0 ||
      Number(item.webThickness) > 0 ||
      Number(item.customCrossSectionArea) > 0 ||
      Number(item.programmingMinutes) > 0 ||
      Number(item.setupMinutes) > 0 ||
      Number(item.machiningMinutes) > 0 ||
      item.surfaceProtectionId ||
      item.heatTreatmentId ||
      item.otherOperationId ||
      String(item.otherCost1Description || "").trim() ||
      String(item.otherCost2Description || "").trim()
    ))
  }

  const getCurrentDraftLabel = () => {
    const customerName = customers.find((item) => item.id === selectedCustomerId)?.naziv || ""
    const firstPosition = positions.find((item) => item.code.trim() || item.name.trim())
    const positionLabel = firstPosition
      ? [firstPosition.code.trim(), firstPosition.name.trim()].filter(Boolean).join(" – ")
      : ""
    return [customerName, positionLabel].filter(Boolean).join(" · ").slice(0, 160)
      || "Nedovršena ponuda"
  }

  const applyDraftState = (rawState: Record<string, unknown>) => {
    const state = rawState as any
    const restoredPositions = Array.isArray(state.positions)
      ? state.positions.map((item: any, index: number) => ({
          ...createEmptyPosition(String(item?.positionNumber || String(index + 1).padStart(3, "0"))),
          ...(item && typeof item === "object" ? item : {}),
        })) as Position[]
      : []

    if (restoredPositions.length > 0) setPositions(restoredPositions)
    setSelectedIndex(Math.max(0, Math.min(
      Number.isInteger(Number(state.selectedIndex)) ? Number(state.selectedIndex) : 0,
      Math.max(0, restoredPositions.length - 1),
    )))
    setSelectedCustomerId(String(state.selectedCustomerId || ""))
    setCustomerInquiryNumber(String(state.customerInquiryNumber || ""))
    setMarginPercent(Number.isFinite(Number(state.marginPercent)) ? Number(state.marginPercent) : 20)
    setTransportIncluded(state.transportIncluded !== false)
    setStaffelPricesEnabled(Boolean(state.staffelPricesEnabled))
    setStaffelMode(state.staffelMode === "per-position" ? "per-position" : "shared")
    setStaffelQuantityText(typeof state.staffelQuantityText === "string" ? state.staffelQuantityText : DEFAULT_STAFFEL_QUANTITIES)
    setPositionLookupStatuses(state.positionLookupStatuses && typeof state.positionLookupStatuses === "object" ? state.positionLookupStatuses : {})
    setPositionImportReviewByCode(state.positionImportReviewByCode && typeof state.positionImportReviewByCode === "object" ? state.positionImportReviewByCode : {})
    setSkipPositionDatabaseSyncCodes(state.skipPositionDatabaseSyncCodes && typeof state.skipPositionDatabaseSyncCodes === "object" ? state.skipPositionDatabaseSyncCodes : {})
    setHistoryPrefillNotice(String(state.historyPrefillNotice || ""))
    setImportPreview(Array.isArray(state.importPreview) ? state.importPreview : null)
    setImportSourceName(String(state.importSourceName || ""))
    setPdfImportConfirmed(Boolean(state.pdfImportConfirmed))
    setExcelImportGrid(Array.isArray(state.excelImportGrid) ? state.excelImportGrid : null)
    setShowExcelColumnMapping(Boolean(state.showExcelColumnMapping))
    setExcelDataStartRow(Number.isInteger(Number(state.excelDataStartRow)) ? Number(state.excelDataStartRow) : 0)
    setExcelColumnMapping(
      state.excelColumnMapping && typeof state.excelColumnMapping === "object"
        ? state.excelColumnMapping
        : { positionNumber: 0, code: 1, name: 2, quantity: 3, priceScale: -1 },
    )
    setImportPositionChecks(
      state.importPositionChecks && typeof state.importPositionChecks === "object"
        ? state.importPositionChecks
        : {},
    )
    setImportPositionCheckUsed(Boolean(state.importPositionCheckUsed))
    setScenarioSellingPrices(
      state.scenarioSellingPrices && typeof state.scenarioSellingPrices === "object"
        ? state.scenarioSellingPrices
        : {},
    )

    const startedAtMs = Number(state.quotePreparationStartedAtMs)
        if (
      Number.isFinite(startedAtMs) &&
      startedAtMs > 0 &&
      !quotePreparationTimerFinalizedRef.current
      ) {
      quotePreparationStartedAtMsRef.current = startedAtMs
      quotePreparationStartedAtRef.current = new Date(startedAtMs)
      quotePreparationTimerFinalizedRef.current = false
      setQuotePreparationElapsedMs(Math.max(0, Date.now() - startedAtMs))
      try {
        window.sessionStorage.setItem(QUOTE_PREPARATION_TIMER_STORAGE_KEY, String(startedAtMs))
      } catch {
        // Firestore/local draft already contains the original start timestamp.
      }
    }

    draftHasStartedRef.current = true
  }

  useEffect(() => {
    if (sourceQuoteId && !quoteSourceLoaded) return

    // Ne koristimo one-shot ref: React StrictMode u razvojnom režimu namjerno
    // ponavlja setup/cleanup efekata. Svaki setup dobija vlastitu aktivnu zastavicu.
    let active = true
    const readLocalDraft = (key: string): (QuoteDraftRecord & { ownerUid: string }) | null => {
      try {
        const raw = window.localStorage.getItem(key)
        if (!raw) return null
        const parsed = JSON.parse(raw)
        if (
          parsed?.draftId !== quoteDraftId ||
          parsed?.ownerUid !== currentUserUid ||
          !parsed?.state ||
          typeof parsed.state !== "object"
        ) return null
        return parsed as QuoteDraftRecord & { ownerUid: string }
      } catch (error) {
        console.warn("Lokalna radna ponuda nije mogla biti pročitana:", error)
        return null
      }
    }

    const readOtherLocalDrafts = (): QuoteDraftRecord[] => {
      const prefix = `cnc_quote_draft_v1_${currentUserUid}_`
      const found: QuoteDraftRecord[] = []
      try {
        for (let index = 0; index < window.localStorage.length; index += 1) {
          const key = window.localStorage.key(index)
          if (!key?.startsWith(prefix)) continue
          const raw = window.localStorage.getItem(key)
          if (!raw) continue
          const parsed = JSON.parse(raw)
          if (
            parsed?.draftId &&
            parsed.draftId !== quoteDraftId &&
            parsed?.ownerUid === currentUserUid &&
            parsed?.state &&
            typeof parsed.state === "object"
          ) found.push(parsed as QuoteDraftRecord)
        }
      } catch (error) {
        console.warn("Lokalne radne ponude nisu mogle biti izlistane:", error)
      }
      return found
    }

    async function initializeDraft() {
      if (!quoteDraftId || !currentUserUid) {
        if (active) {
          setDraftHydrated(true)
          setDraftSelectionResolved(true)
          setDraftSyncStatus("idle")
        }
        return
      }

      setDraftSyncStatus("loading")
      const localCurrent = localDraftStorageKey ? readLocalDraft(localDraftStorageKey) : null
      let remoteCurrent: QuoteDraftRecord | null = null
      let remoteList: QuoteDraftRecord[] = []
      let cloudAvailable = true

      try {
        remoteCurrent = await getQuoteDraft(currentUserUid, quoteDraftId)
      } catch (error) {
        cloudAvailable = false
        console.warn("Radna ponuda nije dostupna iz Firestorea; koristim lokalnu kopiju:", error)
      }

      if (!active) return

      const currentDraft = !remoteCurrent
        ? localCurrent
        : !localCurrent
          ? remoteCurrent
          : (localCurrent.updatedAtMs > remoteCurrent.updatedAtMs ? localCurrent : remoteCurrent)

      if (currentDraft?.state) {
        applyDraftState(currentDraft.state)
        draftCreatedAtMsRef.current = Number(currentDraft.createdAtMs) || Date.now()
        latestDraftEnvelopeRef.current = {
          label: String(currentDraft.label || "Nedovršena ponuda"),
          positionCount: Number(currentDraft.positionCount) || 0,
          createdAtMs: draftCreatedAtMsRef.current,
          updatedAtMs: Number(currentDraft.updatedAtMs) || Date.now(),
          state: currentDraft.state,
        }

        if (localDraftStorageKey && (!localCurrent || currentDraft.updatedAtMs >= localCurrent.updatedAtMs)) {
          try {
            window.localStorage.setItem(localDraftStorageKey, JSON.stringify({
              ...latestDraftEnvelopeRef.current,
              draftId: quoteDraftId,
              ownerUid: currentUserUid,
            }))
          } catch (error) {
            console.warn("Nije moguće osvježiti lokalnu kopiju radne ponude:", error)
          }
        }

        setDraftSyncStatus(remoteCurrent && remoteCurrent.updatedAtMs >= (localCurrent?.updatedAtMs || 0)
          ? "saved"
          : "local")
        setDraftSyncMessage(remoteCurrent ? "Radna ponuda je vraćena iz sigurnosne kopije." : "Radna ponuda je vraćena s ovog računara.")
        setDraftHydrated(true)
        setDraftSelectionResolved(true)
        return
      }

      // Pri otvaranju druge nove ponude nudimo nastavak ranije prekinute.
      // Edit, copy, History-prefill i eksplicitni resume ne prekidamo dodatnim pitanjem.
      const params = new URLSearchParams(window.location.search)
      const shouldOfferRecovery = !sourceQuoteId && !historyPosition && params.get("resume") !== "1"
      if (shouldOfferRecovery) {
        const localOthers = readOtherLocalDrafts()
        if (cloudAvailable) {
          try {
            remoteList = await listQuoteDrafts(currentUserUid)
          } catch (error) {
            console.warn("Lista radnih ponuda nije dostupna iz Firestorea:", error)
          }
        }
        if (!active) return

        const merged = new Map<string, QuoteDraftRecord>()
        for (const draft of [...localOthers, ...remoteList]) {
          if (draft.draftId === quoteDraftId) continue
          const existing = merged.get(draft.draftId)
          if (!existing || draft.updatedAtMs > existing.updatedAtMs) merged.set(draft.draftId, draft)
        }
        const recoveryItems = Array.from(merged.values())
          .filter((draft) => Array.isArray((draft.state as any)?.positions))
          .sort((a, b) => b.updatedAtMs - a.updatedAtMs)
          .slice(0, 10)

        if (recoveryItems.length > 0) {
          setDraftRecoveryItems(recoveryItems)
          setShowDraftRecovery(true)
          setDraftSelectionResolved(false)
        } else {
          setDraftSelectionResolved(true)
        }
      } else {
        setDraftSelectionResolved(true)
      }

      setDraftHydrated(true)
      setDraftSyncStatus(cloudAvailable ? "idle" : "local")
      if (!cloudAvailable) setDraftSyncMessage("Cloud kopija nije dostupna. Provjeri internet vezu i Firestore pravila; lokalno čuvanje ostaje aktivno.")
    }

    void initializeDraft()
    return () => {
      active = false
    }
  }, [
    quoteDraftId,
    currentUserUid,
    localDraftStorageKey,
    sourceQuoteId,
    quoteSourceLoaded,
  ])

  const resumeQuoteDraft = (draft: QuoteDraftRecord) => {
    const params = new URLSearchParams(String((draft.state as any)?.routeSearch || ""))
    params.set("new", draft.draftId)
    params.set("resume", "1")
    navigate(`${location.pathname}?${params.toString()}`, { replace: true, state: null })
  }

  const startAnotherQuoteDraft = () => {
    setShowDraftRecovery(false)
    setDraftSelectionResolved(true)
  }

  const discardRecoveryDraft = async (draft: QuoteDraftRecord) => {
    if (!window.confirm(`Obrisati radnu ponudu "${draft.label || "Nedovršena ponuda"}"?`)) return

    try {
      await deleteQuoteDraft(currentUserUid, draft.draftId)
      try {
        window.localStorage.removeItem(`cnc_quote_draft_v1_${currentUserUid}_${draft.draftId}`)
      } catch (storageError) {
        console.warn("Lokalnu kopiju odbačene radne ponude nije moguće ukloniti:", storageError)
      }
      const remaining = draftRecoveryItems.filter((item) => item.draftId !== draft.draftId)
      setDraftRecoveryItems(remaining)
      if (remaining.length === 0) {
        setShowDraftRecovery(false)
        setDraftSelectionResolved(true)
      }
    } catch (error) {
      console.error("Nije moguće obrisati radnu ponudu:", error)
      alert("Radnu ponudu trenutno nije moguće obrisati. Provjeri internet vezu i pokušaj ponovo.")
    }
  }

  const clearCurrentQuoteDraftAfterSave = async () => {
    finalizedDraftSignatureRef.current = JSON.stringify(buildDraftState())
    if (draftWriteTimerRef.current !== null) {
      window.clearTimeout(draftWriteTimerRef.current)
      draftWriteTimerRef.current = null
    }

    let remoteDraftRemoved = true
    try {
      await draftWriteQueueRef.current.catch(() => undefined)
      if (currentUserUid && quoteDraftId) {
        await deleteQuoteDraft(currentUserUid, quoteDraftId)
      }
    } catch (error) {
      remoteDraftRemoved = false
      console.warn("Ponuda je spremljena, ali cloud radnu kopiju nije bilo moguće ukloniti:", error)
    } finally {
      // Konačna ponuda je već sigurno spremljena u /quotes. Lokalnu kopiju
      // uvijek uklanjamo da se nakon F5 ne bi ponudila kao nova ponuda/duplikat.
      try {
        if (localDraftStorageKey) window.localStorage.removeItem(localDraftStorageKey)
      } catch (error) {
        console.warn("Lokalnu kopiju radne ponude nije moguće ukloniti:", error)
      }
      latestDraftEnvelopeRef.current = null
    }

    if (remoteDraftRemoved) {
      setDraftSyncStatus("saved")
      setDraftSyncMessage("Ponuda je spremljena. Radna kopija je zatvorena.")
    } else {
      setDraftSyncStatus("error")
      setDraftSyncMessage("Ponuda je spremljena. Cloud kopija će biti ponuđena za ručno uklanjanje ako ostane na listi radnih ponuda.")
    }
  }

  useEffect(() => {
    if (!draftHydrated || !draftSelectionResolved || !currentUserUid || !quoteDraftId) return

    const state = buildDraftState()
    const hasContent = isDraftMeaningful()
    if (!draftHasStartedRef.current && !hasContent) return
    draftHasStartedRef.current = true

    const signature = JSON.stringify(state)
    if (finalizedDraftSignatureRef.current === signature) return
    finalizedDraftSignatureRef.current = null

    const updatedAtMs = Date.now()
    const meaningfulPositionCount = positions.filter((item) => item.code.trim() || item.name.trim()).length
    const envelope = {
      label: getCurrentDraftLabel(),
      positionCount: meaningfulPositionCount,
      createdAtMs: draftCreatedAtMsRef.current,
      updatedAtMs,
      state: {
        ...state,
        // Čuva query parametre (npr. edit=ID) kako bi se ista ponuda otvorila pri oporavku.
        routeSearch: window.location.search,
      } as Record<string, unknown>,
    }
    latestDraftEnvelopeRef.current = envelope

    // Lokalna kopija se upisuje odmah, prije debounce-a prema Firestoreu.
    try {
      window.localStorage.setItem(localDraftStorageKey, JSON.stringify({
        ...envelope,
        draftId: quoteDraftId,
        ownerUid: currentUserUid,
      }))
      setDraftSyncStatus("local")
      setDraftSyncMessage("Sačuvano na ovom računaru; sinhronizacija s bazom slijedi.")
    } catch (error) {
      console.error("Nije moguće lokalno sačuvati radnu ponudu:", error)
      setDraftSyncStatus("error")
      setDraftSyncMessage("Preglednik nije dozvolio lokalno čuvanje. Provjeri dozvole i slobodan prostor.")
    }

    if (draftWriteTimerRef.current !== null) {
      window.clearTimeout(draftWriteTimerRef.current)
    }

    draftWriteTimerRef.current = window.setTimeout(() => {
      const pending = latestDraftEnvelopeRef.current
      if (!pending) return
      setDraftSyncStatus("saving")
      setDraftSyncMessage("Sinhronizacija radne ponude s bazom...")
      draftWriteQueueRef.current = draftWriteQueueRef.current
        .catch(() => undefined)
        .then(() => saveQuoteDraft(currentUserUid, quoteDraftId, pending))
        .then(() => {
          if (latestDraftEnvelopeRef.current?.updatedAtMs === pending.updatedAtMs) {
            setDraftSyncStatus("saved")
            setDraftSyncMessage("Automatski sačuvano u Firestore.")
          }
        })
        .catch((error) => {
          console.error("Greška pri automatskom čuvanju radne ponude:", error)
          if (latestDraftEnvelopeRef.current?.updatedAtMs === pending.updatedAtMs) {
            setDraftSyncStatus("error")
            setDraftSyncMessage(navigator.onLine
              ? "Lokalna kopija je sačuvana, ali sinhronizacija s Firestoreom nije uspjela."
              : "Nema interneta. Radna ponuda je sačuvana lokalno i čeka vezu.")
          }
        })
    }, 750)

    return () => {
      if (draftWriteTimerRef.current !== null) {
        window.clearTimeout(draftWriteTimerRef.current)
        draftWriteTimerRef.current = null
      }
    }
  }, [
    draftHydrated,
    draftSelectionResolved,
    currentUserUid,
    quoteDraftId,
    positions,
    selectedIndex,
    selectedCustomerId,
    customerInquiryNumber,
    marginPercent,
    transportIncluded,
    staffelPricesEnabled,
    staffelMode,
    staffelQuantityText,
    positionLookupStatuses,
    positionImportReviewByCode,
    skipPositionDatabaseSyncCodes,
    historyPrefillNotice,
    importPreview,
    importSourceName,
    pdfImportConfirmed,
    excelImportGrid,
    showExcelColumnMapping,
    excelDataStartRow,
    excelColumnMapping,
    importPositionChecks,
    importPositionCheckUsed,
    scenarioSellingPrices,
    customers,
  ])

  // Pokušaj neposredne sinhronizacije pri zatvaranju taba i automatski retry
  // kada se internet vrati. Lokalna kopija je već upisana prije ovog koraka.
  useEffect(() => {
    if (!currentUserUid || !quoteDraftId) return

    const flushLatestDraft = () => {
      const pending = latestDraftEnvelopeRef.current
      if (!pending) return
      if (draftWriteTimerRef.current !== null) {
        window.clearTimeout(draftWriteTimerRef.current)
        draftWriteTimerRef.current = null
      }
      draftWriteQueueRef.current = draftWriteQueueRef.current
        .catch(() => undefined)
        .then(() => saveQuoteDraft(currentUserUid, quoteDraftId, pending))
        .catch((error) => {
          console.warn("Radna ponuda ostaje u lokalnoj kopiji; cloud sync pri izlasku nije potvrđen:", error)
        })
    }

    const handleOnline = () => flushLatestDraft()
    window.addEventListener("pagehide", flushLatestDraft)
    window.addEventListener("beforeunload", flushLatestDraft)
    window.addEventListener("online", handleOnline)

    return () => {
      window.removeEventListener("pagehide", flushLatestDraft)
      window.removeEventListener("beforeunload", flushLatestDraft)
      window.removeEventListener("online", handleOnline)
      flushLatestDraft()
    }
  }, [currentUserUid, quoteDraftId])

  // Preuzimanje jedne pozicije iz Historije kalkulacija u potpuno novu ponudu.
  // Prenose se samo tehnički parametri; cijene i troškovi se ne preuzimaju iz stare ponude.
  useEffect(() => {
    if (!historyPosition || sourceQuoteId) return

    const positionNumber = String(historyPosition.positionNumber || "001")
    const startingPosition: Position = {
      ...createEmptyPosition(positionNumber),
      positionNumber,
      code: String(historyPosition.code || ""),
      name: String(historyPosition.name || ""),
      quantity: Number(historyPosition.quantity) > 0 ? Number(historyPosition.quantity) : 1,
    }
    const loadedPosition = convertDatabasePosition(historyPosition, startingPosition)

    setPositions([loadedPosition])
    setSelectedIndex(0)
    setTransportIncluded(true)
    setSelectedCustomerId("")
    setCustomerInquiryNumber("")
    setSavedQuoteId(null)
    setEditingQuote(null)
    setPositionLookupStatuses({})
    setPositionImportReviewByCode({})
    setSkipPositionDatabaseSyncCodes({})
    setStaffelPricesEnabled(false)
    setStaffelMode("shared")
    setStaffelQuantityText(DEFAULT_STAFFEL_QUANTITIES)

    const sourceNumber = String(historySource?.quoteNumber || "").trim()
    const sourceCustomer = String(historySource?.customerName || "").trim()
    const sourceDescription = [sourceNumber ? `ponude ${sourceNumber}` : "ranije ponude", sourceCustomer ? `kupca ${sourceCustomer}` : ""].filter(Boolean).join(" · ")
    setHistoryPrefillNotice(
      `Preuzeti su tehnički podaci iz ${sourceDescription}. Stara prodajna cijena i troškovi nisu preuzeti: sistem će ih izračunati prema aktuelnim cijenama materijala i satnicama. Odaberi kupca za novu ponudu.`,
    )

    // State koristimo jednokratno; osvježavanje ili povratak na ovu rutu neće ponovo učitavati staru poziciju.
    navigate(`${location.pathname}${location.search}`, { replace: true, state: null })
  }, [historyPosition, historySource, sourceQuoteId, navigate, location.pathname, location.search])

  useEffect(() => {
    async function loadGeneralSettings() {
      try {
        const data = await getGeneralSettings()
        setGeneralSettings(data)
      } catch (error) {
        console.error(
          "Greška pri učitavanju općih postavki:",
          error,
        )
      }
    }

    loadGeneralSettings()
  }, [])

  /*
   * ==========================================
   * AŽURIRANJE TRENUTNE POZICIJE
   * ==========================================
   */

  const updatePosition = (
    changes: Partial<Position>,
  ) => {
    setPositions((current) =>
      current.map(
        (item, index) =>
          index === selectedIndex
            ? {
                ...item,
                ...changes,
              }
            : item,
      ),
    )
  }

  const copyPositionData = (sourceIndex: number) => {
    if (sourceIndex === selectedIndex) return
    const source = positions[sourceIndex]
    if (!source) return

    // Namjerno ne kopiramo identitet pozicije, količinu, maržu, transport,
    // Staffel količine niti prodajnu cijenu. Prenose se ulazi kalkulacije.
    const technicalData: Partial<Position> = {
      shape: source.shape,
      length: source.length,
      width: source.width,
      height: source.height,
      diameter: source.diameter,
      rawWeightKg: source.rawWeightKg,
      rawWeightInput: source.rawWeightInput,
      outputWeightKg: source.outputWeightKg,
      outputWeightInput: source.outputWeightInput,
      thickness: source.thickness,
      flangeThickness: source.flangeThickness,
      webThickness: source.webThickness,
      customCrossSectionArea: source.customCrossSectionArea,
      material: source.material,
      programmingMinutes: source.programmingMinutes,
      setupMinutes: source.setupMinutes,
      machiningMinutes: source.machiningMinutes,
      machine: source.machine,
      machineProgrammingRate: source.machineProgrammingRate,
      machineSetupRate: source.machineSetupRate,
      machineMachiningRate: source.machineMachiningRate,
      surfaceProtectionId: source.surfaceProtectionId,
      surfaceProtectionName: source.surfaceProtectionName,
      heatTreatmentId: source.heatTreatmentId,
      heatTreatmentName: source.heatTreatmentName,
      otherOperationId: source.otherOperationId,
      otherOperationName: source.otherOperationName,
      otherCost1Description: source.otherCost1Description,
      otherCost1Amount: source.otherCost1Amount,
      otherCost2Description: source.otherCost2Description,
      otherCost2Amount: source.otherCost2Amount,
      ensatCount: source.ensatCount,
      inspectionCostSeries: source.inspectionCostSeries,
    }

    setPositions((current) => current.map((item, index) =>
      index === selectedIndex ? { ...item, ...technicalData } : item
    ))
    setCopyPositionSourceIndex(null)
  }

  const handleShapeChange = (nextShape: Shape) => {
    // Pri promjeni presjeka brišemo dimenzije specifične za prethodni oblik.
    // Dužina ostaje sačuvana jer je potrebna za većinu oblika sirovine.
    updatePosition({
      shape: nextShape,
      outputWeightKg: 0,
      outputWeightInput: "",
      width: 0,
      height: 0,
      diameter: 0,
      thickness: 0,
      flangeThickness: 0,
      webThickness: 0,
      customCrossSectionArea: 0,
    })
  }

  /*
   * ==========================================
   * AŽURIRANJE POZICIJE U TABELI
   * ==========================================
   */

  const renderDimensionInput = (
    label: string,
    field: PositionDimensionField,
  ) => (
    <label key={field}>
      {label}
      <LocalizedNumberInput
        min="0"
        step="any"
        value={position[field] || ""}
        onValueChange={(value) => updatePosition({
          [field]: Math.max(0, value),
        } as Partial<Position>)}
      />
    </label>
  )

  const updatePositionAtIndex = (
    index: number,
    changes: Partial<Position>,
  ) => {
    setPositions((current) =>
      current.map(
        (item, itemIndex) =>
          itemIndex === index
            ? {
                ...item,
                ...changes,
              }
            : item,
      ),
    )
  }

  /*
   * ==========================================
   * UČITAVANJE POZICIJE IZ FIREBASE-A
   * ==========================================
   */

  const loadPositionFromDatabase =
    async (
      code: string,
      targetIndex: number = selectedIndex,
    ) => {
      const normalizedCode = code.trim()

      if (!normalizedCode) {
        return false
      }

      setPositionLookupStatus(normalizedCode, "loading")

      try {
        const savedPosition = await getPositionByCode(normalizedCode)

        if (!savedPosition) {
          setPositionLookupStatus(normalizedCode, "not-found")
          return false
        }

        setPositions((current) =>
          current.map((item, index) =>
            index === targetIndex
              ? convertDatabasePosition(savedPosition, {
                  ...item,
                  code: normalizedCode,
                })
              : item,
          ),
        )

        setPositionLookupStatus(normalizedCode, "found")
        setSkipPositionDatabaseSyncCodes((current) => {
          const next = { ...current }
          delete next[normalizePositionLookupCode(normalizedCode)]
          return next
        })
        return true
      } catch (error) {
        console.error(
          "Greška pri učitavanju pozicije:",
          error,
        )

        setPositionLookupStatus(normalizedCode, "error")
        alert(
          "Greška pri učitavanju pozicije iz Firebase-a.",
        )

        return false
      }
    }

  /*
   * ==========================================
   * SPREMANJE POZICIJE
   * ==========================================
   */

const upsertPositionInDatabase = async (positionToSave: Position) => {
  const code = positionToSave.code.trim()

  // A quote is validated before saving, but keep this helper defensive.
  if (!code) {
    return;
  }

  const databasePosition = {
    sifra: code,
    naziv: positionToSave.name,
    kolicina: positionToSave.quantity,
    tipKomada: positionToSave.shape,
    duzina: positionToSave.length,
    sirina: positionToSave.width,
    visina: positionToSave.height,
    precnik: positionToSave.diameter,
    rawWeightKg: Number(positionToSave.rawWeightKg) || 0,
    outputWeightKg: Number(positionToSave.outputWeightKg) || 0,
    debljina: positionToSave.thickness,
    debljinaPrirubnice: positionToSave.flangeThickness,
    debljinaRebra: positionToSave.webThickness,
    povrsinaPresjekaMm2: positionToSave.customCrossSectionArea,
    materijal: positionToSave.material,
    masina: positionToSave.machine,
    programiranje: positionToSave.programmingMinutes,
    priprema: positionToSave.setupMinutes,
    obrada: positionToSave.machiningMinutes,
    inspectionCostSeries:
      Number(positionToSave.inspectionCostSeries) || 0,
    surfaceProtectionId: positionToSave.surfaceProtectionId,
    surfaceProtectionName: positionToSave.surfaceProtectionName,
    heatTreatmentId: positionToSave.heatTreatmentId,
    heatTreatmentName: positionToSave.heatTreatmentName,
    otherOperationId: positionToSave.otherOperationId,
    otherOperationName: positionToSave.otherOperationName,
    otherCost1Description: positionToSave.otherCost1Description,
    otherCost1Amount: Number(positionToSave.otherCost1Amount) || 0,
    otherCost2Description: positionToSave.otherCost2Description,
    otherCost2Amount: Number(positionToSave.otherCost2Amount) || 0,
    ensatCount: Number(positionToSave.ensatCount) || 0,
  };

  const existingPosition = await getPositionByCode(code);

  if (existingPosition?.id) {
    await updatePositionInDatabase(
      existingPosition.id,
      databasePosition,
    );
  } else {
    await addPositionToDatabase(databasePosition);
  }
}

  /*
   * ==========================================
   * UVOZ UPITA IZ EXCELA I PDF-A
   * ==========================================
   */
  const applyCustomerRecognition = (documentText: string, fileName: string): string => {
    const result = detectCustomerFromInquiry(documentText, fileName, customers)
    setCustomerRecognitionSuggestions(result.suggestions)

    const matchedCustomer = result.matchedCustomer
    if (matchedCustomer?.id) {
      if (selectedCustomerId && selectedCustomerId !== matchedCustomer.id) {
        const currentlySelected = customers.find((customer) => customer.id === selectedCustomerId)
        const shouldSwitch = window.confirm(
          `Dokument najvjerovatnije pripada kupcu "${matchedCustomer.naziv}".\n\n` +
          `Trenutno je odabran kupac "${currentlySelected?.naziv || "nepoznat kupac"}".\n\n` +
          `Želiš li promijeniti kupca na "${matchedCustomer.naziv}"?`,
        )

        if (!shouldSwitch) {
          setCustomerRecognitionMessage(
            `Dokument najvjerovatnije pripada kupcu "${matchedCustomer.naziv}", ali je zadržan već odabrani kupac "${currentlySelected?.naziv || "nepoznat kupac"}". Provjeri kupca prije uvoza.`,
          )
          setCustomerRecognitionSuggestions([matchedCustomer])
          return selectedCustomerId
        }
      }

      setSelectedCustomerId(matchedCustomer.id)
      setCustomerRecognitionSuggestions([])
      setCustomerRecognitionMessage(
        `Kupac je automatski prepoznat: "${matchedCustomer.naziv}" (prema ${result.reason}). Ipak provjeri da je kupac ispravan prije snimanja ponude.`,
      )
      return matchedCustomer.id
    }

    if (result.suggestions.length > 0) {
      setCustomerRecognitionMessage(
        "Pronađeno je nekoliko mogućih kupaca, ali podudaranje nije dovoljno sigurno za automatski izbor. Odaberi odgovarajućeg kupca ispod.",
      )
    } else if (selectedCustomerId) {
      const currentCustomer = customers.find((customer) => customer.id === selectedCustomerId)
      setCustomerRecognitionMessage(
        `Kupac nije pouzdano prepoznat iz dokumenta. Zadržan je ručno odabrani kupac "${currentCustomer?.naziv || ""}"; provjeri da odgovara upitu.`,
      )
    } else {
      setCustomerRecognitionMessage(
        "Kupac nije automatski prepoznat iz dokumenta. Odaberi ga ručno u polju 'Kupac' prije snimanja ponude.",
      )
    }

    return selectedCustomerId
  }

  const chooseRecognizedCustomer = (customer: Customer) => {
    if (!customer.id) return
    setSelectedCustomerId(customer.id)
    setCustomerRecognitionSuggestions([])
    setCustomerRecognitionMessage(
      `Ručno je odabran predloženi kupac "${customer.naziv}". Provjeri da odgovara upitu prije snimanja ponude.`,
    )
  }

  const handleInquiryFileSelect = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    // Resetiramo input kako bi se ista datoteka mogla ponovo odabrati.
    event.target.value = ""
    if (!file) return

    setImportSourceName(file.name)
    setImportingInquiry(true)
    setImportError("")
    setImportInfoMessage("")
    setCustomerRecognitionMessage("")
    setCustomerRecognitionSuggestions([])
    setImportPreview(null)
    setImportPositionChecks({})
    setImportPositionCheckUsed(false)
    setExcelImportGrid(null)
    setShowExcelColumnMapping(false)
    setPdfImportConfirmed(false)

    try {
      const isPdf = file.name.toLocaleLowerCase().endsWith(".pdf")
      // Initialize before branch-specific parsing so TypeScript can
      // safely validate all preview/error paths.
      let parsedRows: InquiryImportRow[] = []
      let excelHeaderDetected = true
      let effectiveCustomerId = selectedCustomerId

      if (isPdf) {
        const pdfResult = await extractUniversalPdfInquiry(file)
        effectiveCustomerId = applyCustomerRecognition(pdfResult.text, file.name)
        parsedRows = pdfResult.rows

        // The text-grid route is an automatic fallback inside the same
        // universal parser flow; users never choose a customer-specific parser.
        if (parsedRows.length === 0 && pdfResult.hasTextLayer) {
          parsedRows = parseInquiryGrid(pdfResult.grid, "pdf")
        }

        if (!pdfResult.hasTextLayer) {
          throw new Error(
            "PDF nema prepoznatljiv tekstualni sloj i vjerovatno je skeniran kao slika. Univerzalni parser trenutno obrađuje tekstualne PDF-ove; za ovaj dokument potreban je OCR. Nijedan red nije uvezen.",
          )
        }

        if (parsedRows.length > 0) {
          setImportInfoMessage(
            `PDF je automatski analiziran univerzalnim parserom (${pdfResult.pageCount} str.). Pregled svih pozicija i izričita potvrda prije uvoza su obavezni.`,
          )
        }
      } else {
        const workbook = XLSX.read(await file.arrayBuffer(), { type: "array", raw: false })
        const firstSheetName = workbook.SheetNames[0]
        if (!firstSheetName) throw new Error("Excel datoteka nema nijedan radni list.")
        const worksheet = workbook.Sheets[firstSheetName]
        const grid = (XLSX.utils.sheet_to_json(worksheet, {
          header: 1,
          raw: false,
          defval: "",
          blankrows: false,
        }) as unknown[][]).map((row) => row.map((cell) => String(cell ?? "")))

        setExcelImportGrid(grid)
        effectiveCustomerId = applyCustomerRecognition(grid.map((row) => row.join(" ")).join("\n"), file.name)
        const detectedHeader = findBestInquiryHeader(grid)
        excelHeaderDetected = detectedHeader.headerIndex >= 0
        let activeMapping = detectedHeader.headerIndex >= 0
          ? detectedHeader.headerMap
          : { positionNumber: 0, code: 1, name: 2, quantity: 3, priceScale: -1 }
        let activeStartRow = detectedHeader.headerIndex >= 0 ? detectedHeader.headerIndex + 1 : 0

        // Ako je kupac odabran, pokušaj prvo primijeniti njegov ranije spremljeni profil.
        // Profil se i dalje provjerava u preview prozoru prije stvarnog uvoza.
        let profileApplied = false
        if (effectiveCustomerId) {
          const savedProfile = await loadSavedExcelImportProfile(effectiveCustomerId)
          if (savedProfile && isExcelImportProfileCompatible(savedProfile, grid)) {
            const profileMapping: ExcelColumnMapping = {
              ...savedProfile.mapping,
              priceScale: savedProfile.mapping.priceScale === undefined
                ? detectedHeader.headerMap.priceScale
                : savedProfile.mapping.priceScale,
            }
            const profileRows = parseExcelGridByMapping(
              grid,
              savedProfile.dataStartRow,
              profileMapping,
            )
            if (profileRows.length > 0) {
              activeMapping = profileMapping
              activeStartRow = savedProfile.dataStartRow
              parsedRows = profileRows
              profileApplied = true
              excelHeaderDetected = true
              setImportInfoMessage(
                `Automatski je primijenjen spremljeni Excel format za kupca "${savedProfile.customerName}". Provjeri podatke prije uvoza.`,
              )
            }
          } else if (savedProfile) {
            setImportInfoMessage(
              `Pronađen je spremljeni format za kupca, ali se ne uklapa u ovu datoteku. Koristi se automatsko prepoznavanje; po potrebi podesi kolone ručno i ponovo sačuvaj format.`,
            )
          }
        }

        setExcelDataStartRow(activeStartRow)
        setExcelColumnMapping(activeMapping)

        if (!profileApplied) {
          parsedRows = parseInquiryGrid(grid, "excel")
          if (!parsedRows.length) {
            setImportSourceName(file.name)
            setImportPreview([])
            setShowExcelColumnMapping(true)
            setImportError("Automatsko prepoznavanje nije pronašlo pozicije. Odredi kolone ručno i potvrdi početni red podataka.")
            return
          }
        }
      }

      if (!parsedRows.length) {
        if (isPdf) {
          throw new Error(
            "Univerzalni PDF parser nije pronašao dovoljno jasnu tabelu pozicija. Provjeri da dokument ima zaglavlja koja označavaju šifru/artikl i količinu (npr. Pos., Material, Artikelnummer, Description, Menge ili Required Qty). Nijedan red nije uvezen kako se broj upita, datum, adresa ili kontakt ne bi pogrešno protumačili kao artikl.",
          )
        }
        throw new Error("Nije pronađena nijedna pozicija u datoteci.")
      }

      setImportSourceName(file.name)
      setImportPreview(parsedRows)

      if (!isPdf) {
        const emptyNames = parsedRows.filter((row) => !row.name.trim()).length
        if (!excelHeaderDetected && !importInfoMessage) {
          setShowExcelColumnMapping(true)
          setImportError("Nisam pouzdano prepoznao zaglavlje kolona. Automatski rezultat je samo prijedlog; podesi kolone i početni red prije uvoza.")
        } else if (emptyNames >= Math.ceil(parsedRows.length / 2) && !importInfoMessage) {
          setShowExcelColumnMapping(true)
          setImportError("Automatsko prepoznavanje je našlo redove, ali većina naziva je prazna. Provjeri ručno odabrane kolone prije uvoza.")
        }
      }
    } catch (error) {
      console.error("Greška pri učitavanju upita:", error)
      setImportError(error instanceof Error ? error.message : "Datoteku nije moguće pročitati.")
    } finally {
      setImportingInquiry(false)
    }
  }

  const updateImportPreviewRow = (index: number, changes: Partial<InquiryImportRow>) => {
    if (importSourceName.toLocaleLowerCase().endsWith(".pdf")) {
      setPdfImportConfirmed(false)
    }
    setImportPreview((current) => current
      ? current.map((row, rowIndex) => rowIndex === index ? { ...row, ...changes } : row)
      : current,
    )

    // Ako se izmijeni šifra ili naziv, rezultat poređenja te pozicije više
    // nije pouzdan i mora se ponovo provjeriti. Izmjena količine ga ne poništava.
    if (Object.prototype.hasOwnProperty.call(changes, "code") ||
        Object.prototype.hasOwnProperty.call(changes, "name")) {
      setImportPositionChecks((current) => {
        const next = { ...current }
        delete next[index]
        return next
      })
    }
  }

  const applyManualExcelMapping = () => {
    if (!excelImportGrid) return
    if (excelColumnMapping.code < 0 || excelColumnMapping.name < 0 || excelColumnMapping.quantity < 0) {
      setImportError("Obavezno odaberi kolone Šifra, Naziv i Količina. Broj pozicije je opcionalan.")
      return
    }

    const mappedRows = parseExcelGridByMapping(excelImportGrid, excelDataStartRow, excelColumnMapping)
    if (!mappedRows.length) {
      setImportPreview([])
      setImportError("Iz odabranih kolona nije pronađena nijedna pozicija. Provjeri kolone i početni red podataka.")
      return
    }

    setImportPreview(mappedRows)
    setImportPositionChecks({})
    setImportPositionCheckUsed(false)
    setShowExcelColumnMapping(false)
    setImportError("")
  }


  const saveExcelImportProfile = async () => {
    if (!selectedCustomerId) {
      setImportError("Prvo odaberi kupca na vrhu stranice kako bi format bio povezan s njim.")
      return
    }
    if (!excelImportGrid) {
      setImportError("Prvo učitaj Excel upit i odredi kolone koje treba zapamtiti.")
      return
    }
    if (
      excelColumnMapping.code < 0 ||
      excelColumnMapping.name < 0 ||
      excelColumnMapping.quantity < 0
    ) {
      setImportError("Za spremanje formata obavezno odaberi kolone Šifra, Naziv i Količina.")
      setShowExcelColumnMapping(true)
      return
    }
    if (excelDataStartRow < 0 || excelDataStartRow >= excelImportGrid.length) {
      setImportError("Odabrani prvi red podataka nije valjan.")
      return
    }

    const selectedCustomer = customers.find((customer) => customer.id === selectedCustomerId)
    const customerName = selectedCustomer?.naziv || "Odabrani kupac"
    const profile: SavedExcelImportProfile = {
      customerId: selectedCustomerId,
      customerName,
      format: "excel",
      dataStartRow: excelDataStartRow,
      mapping: { ...excelColumnMapping },
      sourceFileName: importSourceName,
      savedAt: new Date().toISOString(),
    }

    setSavingImportProfile(true)
    setImportError("")
    setImportInfoMessage("")

    // Lokalna kopija je sigurnosni fallback. Kada administratorska prava
    // dozvoljavaju, kopija u Firestore-u sinhronizuje se sa drugim računarima.
    try {
      window.localStorage.setItem(
        `${IMPORT_PROFILE_STORAGE_PREFIX}${selectedCustomerId}`,
        JSON.stringify(profile),
      )
    } catch (error) {
      console.warn("Nije moguće sačuvati lokalnu kopiju profila uvoza:", error)
    }

    try {
      await api.put(`/settings/${getImportProfileDocumentId(selectedCustomerId)}`, { value: profile })
      setImportInfoMessage(
        `Excel format je sačuvan za kupca "${customerName}". Sljedeći put će se automatski primijeniti i bit će dostupan na drugim računarima.`,
      )
    } catch (error) {
      console.warn("Zajedničko spremanje Excel profila nije uspjelo; korištena je lokalna kopija:", error)
      setImportInfoMessage(
        `Format je sačuvan lokalno za kupca "${customerName}". Dijeljeno spremanje nije uspjelo; na ovom računaru će se automatski primjenjivati.`,
      )
    } finally {
      setSavingImportProfile(false)
    }
  }

  const checkImportedPositions = async () => {
    if (!importPreview?.length || checkingImportPositions || importingInquiry) return

    setCheckingImportPositions(true)
    setImportError("")
    setImportInfoMessage("")

    try {
      const results = await Promise.all(importPreview.map(async (row): Promise<ImportPositionDatabaseCheck> => {
        const code = row.code.trim()
        if (!code) {
          return {
            status: "missing-code",
            databaseName: "",
            nameMismatch: false,
            useDatabaseData: false,
          }
        }

        try {
          const savedPosition = await getPositionByCode(code)
          if (!savedPosition) {
            return {
              status: "new",
              databaseName: "",
              nameMismatch: false,
              useDatabaseData: false,
            }
          }

          const databaseName = String(savedPosition.naziv || "").trim()
          const inquiryName = row.name.trim()
          const nameMismatch = Boolean(
            databaseName && inquiryName &&
            normalizeComparablePositionName(databaseName) !== normalizeComparablePositionName(inquiryName)
          )

          return {
            status: "found",
            savedPosition,
            databaseName,
            nameMismatch,
            // Kod razlike u nazivu ostavljamo izbor kalkulantu umjesto
            // da automatski preuzimamo tehničke podatke potencijalno drugog artikla.
            useDatabaseData: !nameMismatch,
          }
        } catch (error) {
          console.error(`Provjera pozicije ${code} nije uspjela:`, error)
          return {
            status: "error",
            databaseName: "",
            nameMismatch: false,
            useDatabaseData: false,
          }
        }
      }))

      const nextChecks: Record<number, ImportPositionDatabaseCheck> = {}
      results.forEach((result, index) => { nextChecks[index] = result })
      setImportPositionChecks(nextChecks)
      setImportPositionCheckUsed(true)

      const found = results.filter((result) => result.status === "found").length
      const newPositions = results.filter((result) => result.status === "new").length
      const mismatches = results.filter((result) => result.nameMismatch).length
      const errors = results.filter((result) => result.status === "error").length
      const missingCodes = results.filter((result) => result.status === "missing-code").length

      setImportInfoMessage(
        `Provjera baze završena: ${found} poznatih pozicija, ${newPositions} novih` +
        (mismatches ? `, ${mismatches} s razlikom u nazivu` : "") +
        (errors ? `, ${errors} s greškom provjere` : "") +
        (missingCodes ? `, ${missingCodes} bez šifre` : "") +
        `. Postojeći tehnički podaci se ne mijenjaju u bazi. Provjeri upozorenja i odaberi da li će se podaci iz baze koristiti za svaku poziciju.`,
      )
    } finally {
      setCheckingImportPositions(false)
    }
  }

  const setUseImportDatabaseData = (index: number, useDatabaseData: boolean) => {
    if (importSourceName.toLocaleLowerCase().endsWith(".pdf")) {
      setPdfImportConfirmed(false)
    }
    setImportPositionChecks((current) => {
      const existing = current[index]
      if (!existing || existing.status !== "found") return current
      return { ...current, [index]: { ...existing, useDatabaseData } }
    })
  }

  const applyInquiryImport = async () => {
    if (!importPreview?.length || importingInquiry) return

    const isPdfImport = importSourceName.toLocaleLowerCase().endsWith(".pdf")
    if (isPdfImport && !pdfImportConfirmed) {
      setImportError("Prije uvoza obavezno pregledaj PDF pozicije i označi da potvrđuješ podatke.")
      return
    }

    if (isPdfImport && importPreview.some((row, index) =>
      !row.name.trim() && !importPositionChecks[index]?.useDatabaseData
    )) {
      setImportError("Neki PDF redovi nemaju naziv. Unesi naziv ili provjeri poziciju u bazi i potvrdi korištenje naziva iz baze.")
      return
    }

    if (importPositionCheckUsed && Object.keys(importPositionChecks).length !== importPreview.length) {
      setImportError("Neke šifre ili nazivi su izmijenjeni nakon provjere baze. Ponovo klikni 'Provjeri pozicije u bazi' prije uvoza.")
      return
    }

    if (importValidation.blockingCount > 0) {
      setImportError("Prije uvoza ispravi sve redove označene kao 'Nedostaje šifra' ili 'Neispravna količina'.")
      return
    }

    const hasCurrentData = positions.some((item) =>
      item.code.trim() || item.name.trim() || String(item.staffelQuantityText || "").trim() ||
      item.length > 0 || item.width > 0 || item.height > 0 || item.diameter > 0,
    )

    if (hasCurrentData && !window.confirm(
      "Uvoz će zamijeniti trenutni spisak pozicija u ovoj ponudi. Želiš li nastaviti?",
    )) return

    setImportingInquiry(true)
    setImportError("")
    setPositionLookupStatuses({})
    setPositionImportReviewByCode({})

    try {
      const reviewsByCode: Record<string, ImportedPositionReview> = {}
      const importedPositions = await Promise.all(importPreview.map(async (item, index) => {
        const code = item.code.trim()
        const basePosition: Position = {
          ...createEmptyPosition(item.positionNumber.trim() || String(index + 1).padStart(3, "0")),
          code,
          name: item.name.trim(),
          quantity: Number(item.quantity) > 0 ? Number(item.quantity) : 1,
          staffelQuantityText: String(item.staffelQuantityText || ""),
        }

        if (!code) return basePosition

        const normalizedCode = normalizePositionLookupCode(code)
        const checkedRow = importPositionChecks[index]
        if (checkedRow) {
          reviewsByCode[normalizedCode] = {
            databaseName: checkedRow.databaseName || "",
            importedName: item.name || "",
            nameMismatch: checkedRow.nameMismatch,
            useDatabaseData: checkedRow.useDatabaseData,
            status: checkedRow.status,
          }
        }

        if (checkedRow?.status === "found") {
          if (!checkedRow.useDatabaseData) {
            setPositionLookupStatus(code, "found-skipped")
            return basePosition
          }

          setPositionLookupStatus(code, "found")
          return convertDatabasePosition(checkedRow.savedPosition, basePosition)
        }
        if (checkedRow?.status === "new") {
          setPositionLookupStatus(code, "not-found")
          return basePosition
        }
        if (checkedRow?.status === "error" || checkedRow?.status === "missing-code") {
          setPositionLookupStatus(code, "error")
          return basePosition
        }

        // Čak i kada korisnik preskoči dugme za provjeru u prozoru uvoza,
        // provjeravamo naziv tokom uvoza. Kod razlike ne preuzimamo tehničke
        // podatke tiho, nego prikazujemo upozorenje u glavnoj tabeli.
        setPositionLookupStatus(code, "loading")
        try {
          const savedPosition = await getPositionByCode(code)
          if (!savedPosition) {
            setPositionLookupStatus(code, "not-found")
            reviewsByCode[normalizedCode] = {
              databaseName: "",
              importedName: item.name || "",
              nameMismatch: false,
              useDatabaseData: false,
              status: "new",
            }
            return basePosition
          }

          const databaseName = String(savedPosition.naziv || "").trim()
          const importedName = String(item.name || "").trim()
          const nameMismatch = Boolean(
            databaseName && importedName &&
            normalizeComparablePositionName(databaseName) !== normalizeComparablePositionName(importedName)
          )
          reviewsByCode[normalizedCode] = {
            databaseName,
            importedName,
            nameMismatch,
            useDatabaseData: !nameMismatch,
            status: "found",
          }

          if (nameMismatch) {
            setPositionLookupStatus(code, "found-skipped")
            return basePosition
          }

          setPositionLookupStatus(code, "found")
          return convertDatabasePosition(savedPosition, basePosition)
        } catch (error) {
          console.error(`Prepoznavanje šifre ${code} nije uspjelo:`, error)
          setPositionLookupStatus(code, "error")
          reviewsByCode[normalizedCode] = {
            databaseName: "",
            importedName: item.name || "",
            nameMismatch: false,
            useDatabaseData: false,
            status: "error",
          }
          return basePosition
        }
      }))

      const protectedCodes: Record<string, boolean> = {}
      importPreview.forEach((item, index) => {
        const check = importPositionChecks[index]
        const normalizedCode = normalizePositionLookupCode(item.code)
        if (normalizedCode && check?.status === "found" && !check.useDatabaseData) {
          protectedCodes[normalizedCode] = true
        }
      })
      setSkipPositionDatabaseSyncCodes(protectedCodes)

      setPositionImportReviewByCode(reviewsByCode)

      setPositions(importedPositions)
      const importedPriceScaleCount = importedPositions.filter((item) => parseStaffelQuantities(item.staffelQuantityText || "").length > 0).length
      if (importedPriceScaleCount > 0) {
        setStaffelPricesEnabled(true)
        setStaffelMode("per-position")
        setImportInfoMessage(`Prepoznate su dodatne količine (Price Scale) za ${importedPriceScaleCount} pozicija. Količine možeš provjeriti i izmijeniti u tabeli pozicija.`)
      }
      setSelectedIndex(0)
      setImportPreview(null)
      setImportPositionChecks({})
      setImportPositionCheckUsed(false)
      setImportError("")
      window.alert(
        `Uvezeno je ${importedPositions.length} pozicija. Za poznate šifre tehnički podaci su učitani kada su nazivi odgovarali. Kod razlike u nazivu podaci nisu automatski preuzeti i upozorenje ostaje vidljivo u glavnoj tabeli. Provjeri podatke i kalkulaciju prije snimanja ponude.`,
      )
    } catch (error) {
      console.error("Greška pri primjeni uvezenih pozicija:", error)
      setImportError("Uvoz pozicija nije uspio. Pokušaj ponovo ili provjeri konzolu.")
    } finally {
      setImportingInquiry(false)
    }
  }

  /*
   * ==========================================
   * PASTE IZ EXCELA
   * ==========================================
   * Učitava sve kopirane redove iz Excela.
   * Ako šifra već postoji u bazi, popunjava
   * prethodno sačuvane tehničke podatke pozicije.
   */
  const handlePaste = async (
    event: ClipboardEvent<HTMLTextAreaElement>,
  ) => {
    const text = event.clipboardData.getData("text/plain")

    // Običan tekst bez tabulatora nije Excel tabela;
    // tada prepuštamo browseru standardno lijepljenje.
    if (!text.includes("\t")) {
      return
    }

    event.preventDefault()

    const pastedPositions = parseExcelPaste(text)
    if (pastedPositions.length === 0) {
      return
    }

    setPositionLookupStatuses({})
    setPositionImportReviewByCode({})
    setSkipPositionDatabaseSyncCodes({})

    try {
      const newPositions = await Promise.all(
        pastedPositions.map(async (item) => {
          const normalizedCode = item.code.trim()
          const basePosition: Position = {
            ...createEmptyPosition(item.positionNumber),
            code: normalizedCode,
            name: item.name,
            quantity: item.quantity,
            staffelQuantityText: String(item.staffelQuantityText || ""),
          }

          if (!normalizedCode) {
            return basePosition
          }

          setPositionLookupStatus(normalizedCode, "loading")
          const savedPosition = await getPositionByCode(normalizedCode)

          if (!savedPosition) {
            setPositionLookupStatus(normalizedCode, "not-found")
            return basePosition
          }

          setPositionLookupStatus(normalizedCode, "found")
          return convertDatabasePosition(savedPosition, basePosition)
        }),
      )

      setPositions(newPositions)
      const pastedPriceScaleCount = newPositions.filter((item) => parseStaffelQuantities(item.staffelQuantityText || "").length > 0).length
      if (pastedPriceScaleCount > 0) {
        setStaffelPricesEnabled(true)
        setStaffelMode("per-position")
      }
      setSelectedIndex(0)
    } catch (error) {
      console.error("Greška pri učitavanju pozicija iz Excela:", error)
      pastedPositions.forEach((item) => {
        if (item.code.trim()) {
          setPositionLookupStatus(item.code, "error")
        }
      })
      window.alert(
        "Nije uspjelo učitavanje pozicija iz Excela. Provjeri konzolu za detalje.",
      )
    }
  }

  /*
   * ==========================================
   * DODAVANJE POZICIJE
   * ==========================================
   */

  const addPosition = () => {
    const newPosition =
      createEmptyPosition(
        String(
          positions.length + 1,
        ).padStart(3, "0"),
      )

    setPositions([
      ...positions,
      newPosition,
    ])

    setSelectedIndex(
      positions.length,
    )
  }

  /*
   * ==========================================
   * BRISANJE POZICIJE
   * ==========================================
   */

  const removePosition = () => {
    if (positions.length === 1) {
      return
    }

    const newPositions =
      positions.filter(
        (_, index) =>
          index !== selectedIndex,
      )

    setPositions(
      newPositions,
    )

    setSelectedIndex(
      Math.min(
        selectedIndex,
        newPositions.length - 1,
      ),
    )
  }

  /*
   * ==========================================
   * NAVIGACIJA
   * ==========================================
   */

  const goPrevious = () => {
    setSelectedIndex(
      (current) =>
        current === 0
          ? positions.length - 1
          : current - 1,
    )
  }

  const goNext = () => {
    setSelectedIndex(
      (current) =>
        current ===
        positions.length - 1
          ? 0
          : current + 1,
    )
  }

  /*
   * ==========================================
   * POVRŠINSKA ZAŠTITA
   * ==========================================
   */

  const handleSurfaceProtectionChange = (
    id: string,
  ) => {
    const selectedProtection =
      surfaceProtections.find(
        (item) =>
          item.id === id,
      )

    updatePosition({
      surfaceProtectionId:
        selectedProtection?.id || "",

      surfaceProtectionName:
        selectedProtection?.naziv || "",
    })
  }

  /*
   * ==========================================
   * TERMIČKA OBRADA
   * ==========================================
   */

  const handleHeatTreatmentChange = (
    id: string,
  ) => {
    const selectedTreatment =
      heatTreatments.find(
        (item) =>
          item.id === id,
      )

    updatePosition({
      heatTreatmentId:
        selectedTreatment?.id || "",

      heatTreatmentName:
        selectedTreatment?.naziv || "",
    })
  }


  /*
   * ==========================================
   * OSTALI ZAHVAT
   * ==========================================
   */

  const handleOtherOperationChange = (id: string) => {
    const selectedOperation = otherOperations.find((item) => item.id === id)

    updatePosition({
      otherOperationId: selectedOperation?.id || "",
      otherOperationName: selectedOperation?.naziv || "",
    })
  }

  /*
   * ==========================================
   * KALKULACIJA
   * ==========================================
   */

  const calculations =
    useMemo(() => {
      const material =
        materials[position.material] || defaultMaterials.S235

      const rawGeometryWarning = getShapeDimensionWarning(position)
      const crossSectionAreaMm2 = calculateCrossSectionAreaMm2(position)
      const volumeMm3 = calculateStockVolumeMm3(position)
      const volumeM3 = volumeMm3 / 1_000_000_000

      const calculatedRawWeightKg = volumeM3 * material.density
      const enteredRawWeightKg = Math.max(0, Number(position.rawWeightKg) || 0)
      const weightKg = enteredRawWeightKg > 0 ? enteredRawWeightKg : calculatedRawWeightKg
      // Ako nije unesena ručna ulazna težina, geometrija mora dati validnu masu.
      // Ručni unos ulazne težine omogućava kalkulaciju i bez dimenzija.
      const geometryWarning = enteredRawWeightKg > 0
        ? ""
        : rawGeometryWarning || (calculatedRawWeightKg <= 0
          ? "Unesi dimenzije sirovine ili ručno unesi težinu sirovog komada [kg]."
          : "")
      // Izlazna težina (ručni unos ili ulazna kao fallback) osnova je za transport i tretmane.
      const enteredOutputWeightKg = Number(position.outputWeightKg) || 0
      const outputWeightKg = enteredOutputWeightKg > 0 ? enteredOutputWeightKg : weightKg

      const materialCost =
        weightKg * material.pricePerKg

      const baseMachine =
        machines[position.machine] ||
        defaultMachines[position.machine] ||
        defaultMachines.Haas_UMC500 ||
        { name: position.machine || "Nepoznata mašina", programmingHourlyRate: 35, setupHourlyRate: 0, machiningHourlyRate: 0 }
      const machine: MachineRate = {
        name: baseMachine.name,
        programmingHourlyRate: typeof position.machineProgrammingRate === "number" && Number.isFinite(position.machineProgrammingRate)
          ? Math.max(0, position.machineProgrammingRate)
          : baseMachine.programmingHourlyRate,
        setupHourlyRate: typeof position.machineSetupRate === "number" && Number.isFinite(position.machineSetupRate)
          ? Math.max(0, position.machineSetupRate)
          : baseMachine.setupHourlyRate,
        machiningHourlyRate: typeof position.machineMachiningRate === "number" && Number.isFinite(position.machineMachiningRate)
          ? Math.max(0, position.machineMachiningRate)
          : baseMachine.machiningHourlyRate,
      }

      const quantity =
        Math.max(1, Number(position.quantity) || 1)

      // Programiranje i priprema su troškovi cijele serije.
      const programmingSeriesCost =
        (position.programmingMinutes / 60) * machine.programmingHourlyRate

      const setupSeriesCost =
        (position.setupMinutes / 60) *
        machine.setupHourlyRate

      const programmingCost =
        programmingSeriesCost / quantity

      const setupCost =
        setupSeriesCost / quantity

      // Obrada je trošak po komadu.
      const machiningCost =
        (position.machiningMinutes / 60) *
        machine.machiningHourlyRate

      // Kontrola je opcionalni trošak cijele serije.
      const inspectionCostSeries =
        Number(position.inspectionCostSeries) || 0

      const inspectionCost =
        inspectionCostSeries / quantity

      // Transport se računa po kg.
      const effectiveTransportIncluded = typeof position.transportIncluded === "boolean"
        ? position.transportIncluded
        : transportIncluded
      const effectiveTransportPricePerKg = Number.isFinite(Number(position.transportPricePerKg))
        ? Math.max(0, Number(position.transportPricePerKg))
        : Math.max(0, Number(generalSettings.transportPricePerKg) || 0)
      const transportCost = effectiveTransportIncluded
        ? outputWeightKg * effectiveTransportPricePerKg
        : 0

      const selectedProtection =
        surfaceProtections.find(
          (item) =>
            item.id ===
            position.surfaceProtectionId,
        )

      let surfaceProtectionCost = 0

      if (selectedProtection) {
        const cijena =
          Number(selectedProtection.cijena) || 0

        if (selectedProtection.tipCijene === "komad") {
          surfaceProtectionCost = cijena
        }

        if (selectedProtection.tipCijene === "kg") {
          surfaceProtectionCost =
            cijena * outputWeightKg
        }

        if (selectedProtection.tipCijene === "fiksno") {
          surfaceProtectionCost =
            cijena / quantity
        }
      }

      const selectedHeatTreatment =
        heatTreatments.find(
          (item) =>
            item.id ===
            position.heatTreatmentId,
        )

      let heatTreatmentCost = 0

      if (selectedHeatTreatment) {
        const cijena =
          Number(selectedHeatTreatment.cijena) || 0

        if (selectedHeatTreatment.tipCijene === "komad") {
          heatTreatmentCost = cijena
        }

        if (selectedHeatTreatment.tipCijene === "kg") {
          heatTreatmentCost =
            cijena * outputWeightKg
        }

        if (selectedHeatTreatment.tipCijene === "fiksno") {
          heatTreatmentCost =
            cijena / quantity
        }
      }

      const selectedOtherOperation = otherOperations.find(
        (item) => item.id === position.otherOperationId,
      )
      let otherOperationCost = 0
      if (selectedOtherOperation) {
        const cijena = Number(selectedOtherOperation.cijena) || 0
        if (selectedOtherOperation.tipCijene === "komad") otherOperationCost = cijena
        if (selectedOtherOperation.tipCijene === "kg") otherOperationCost = cijena * weightKg
        if (selectedOtherOperation.tipCijene === "fiksno") otherOperationCost = cijena / quantity
      }

      const ensatCount = Math.max(0, Number(position.ensatCount) || 0)
      const ensatCostPerPiece = ensatCount * (Number(generalSettings.ensatPricePerPiece) || 0)
      const otherCost1Amount = Math.max(0, Number(position.otherCost1Amount) || 0)
      const otherCost2Amount = Math.max(0, Number(position.otherCost2Amount) || 0)
      const otherCostsTotal = otherCost1Amount + otherCost2Amount

      const totalCostPerPiece =
        materialCost +
        programmingCost +
        setupCost +
        machiningCost +
        inspectionCost +
        transportCost +
        surfaceProtectionCost +
        heatTreatmentCost +
        otherOperationCost +
        ensatCostPerPiece +
        otherCostsTotal

      const effectiveMarginPercent = Number.isFinite(Number(position.marginPercent))
        ? Number(position.marginPercent)
        : marginPercent
      const safeMarginPercent =
        Math.min(
          200,
          Math.max(0, effectiveMarginPercent),
        )

      const marginAmount =
        totalCostPerPiece *
        (safeMarginPercent / 100)

      const sellingPricePerPiece =
        totalCostPerPiece +
        marginAmount

      const marginValuePerPiece =
        sellingPricePerPiece -
        totalCostPerPiece

      const totalSellingPrice =
        sellingPricePerPiece * quantity

      const totalCost =
        totalCostPerPiece * quantity

      return {
        volumeM3,
        crossSectionAreaMm2,
        geometryWarning,
        weightKg,
        outputWeightKg,
        materialCost,
        programmingCost,
        setupCost,
        programmingSeriesCost,
        setupSeriesCost,
        machiningCost,
        inspectionCostSeries,
        inspectionCost,
        transportCost,
        surfaceProtectionCost,
        heatTreatmentCost,
        otherOperationCost,
        ensatCount,
        ensatCostPerPiece,
        otherCost1Amount,
        otherCost2Amount,
        otherCostsTotal,
        totalCostPerPiece,
        totalCost,
        machineRate: machine.machiningHourlyRate,
        machineProgrammingRate: machine.programmingHourlyRate,
        machineSetupRate: machine.setupHourlyRate,
        machineMachiningRate: machine.machiningHourlyRate,
        machineName: machine.name,
        marginAmount,
        sellingPricePerPiece,
        marginValuePerPiece,
        totalSellingPrice,
        safeMarginPercent,
      }
    }, [
      position,
      marginPercent,
      surfaceProtections,
      heatTreatments,
      otherOperations,
      generalSettings.transportPricePerKg,
      generalSettings.ensatPricePerPiece,
      transportIncluded,
      materials,
      machines,
    ])

  // Svaki Staffelpreis se izračunava ponovo za svoju količinu, tako da se
  // troškovi programiranja, pripreme i kontrole pravilno rasporede na seriju.
  const activePositionStaffelQuantities = staffelMode === "shared"
    ? staffelQuantities
    : parseStaffelQuantities(position?.staffelQuantityText || "")
  const activePositionStaffelInputInvalid = staffelMode === "shared"
    ? staffelQuantityInputInvalid
    : hasInvalidStaffelQuantity(position?.staffelQuantityText || "")

  const positionStaffelPrices = useMemo(() => {
    if (!staffelPricesEnabled || !position || activePositionStaffelQuantities.length === 0) return []

    return activePositionStaffelQuantities.map((quantity) => {
      const tier = calculatePositionCostsForSave(
        { ...position, quantity },
        marginPercent,
        surfaceProtections,
        heatTreatments,
        otherOperations,
        generalSettings,
        materials,
        machines,
        transportIncluded,
      )

      return {
        quantity,
        totalCostPerPiece: tier.totalCostPerPiece,
        totalCost: tier.totalCost,
        sellingPricePerPiece: tier.sellingPricePerPiece,
        totalSellingPrice: tier.totalSellingPrice,
        marginValuePerPiece: tier.marginValuePerPiece,
      }
    })
  }, [
    staffelPricesEnabled,
    staffelMode,
    activePositionStaffelQuantities.join(","),
    position,
    marginPercent,
    surfaceProtections,
    heatTreatments,
    otherOperations,
    generalSettings,
    transportIncluded,
    materials,
    machines,
  ])

  // Ukupna profitabilnost svih pozicija računa se istom funkcijom
  // koja se koristi pri spremanju ponude, bez mijenjanja postojeće formule.
  const liveQuoteProfitability = useMemo(() => {
    const items = positions.map((item) => {
      const itemCalculations = calculatePositionCostsForSave(
        item,
        marginPercent,
        surfaceProtections,
        heatTreatments,
        otherOperations,
        generalSettings,
        materials,
        machines,
        transportIncluded,
      )

      return {
        quantity: Math.max(1, Number(item.quantity) || 1),
        totalCost: itemCalculations.totalCost,
        totalSellingPrice: itemCalculations.totalSellingPrice,
        profit: itemCalculations.totalSellingPrice - itemCalculations.totalCost,
      }
    })

    const totalCost = items.reduce((sum, item) => sum + item.totalCost, 0)
    const totalSellingPrice = items.reduce(
      (sum, item) => sum + item.totalSellingPrice,
      0,
    )
    const totalProfit = totalSellingPrice - totalCost

    return {
      items,
      totalCost,
      totalSellingPrice,
      totalProfit,
      profitPercentOfSales: totalSellingPrice > 0
        ? (totalProfit / totalSellingPrice) * 100
        : 0,
    }
  }, [
    positions,
    marginPercent,
    surfaceProtections,
    heatTreatments,
    otherOperations,
    generalSettings.transportPricePerKg,
    generalSettings.ensatPricePerPiece,
    transportIncluded,
    materials,
    machines,
  ])

  const scenarioPriceKey = `${position.positionNumber || selectedIndex}-${normalizePositionLookupCode(position.code || "")}`
  const scenarioSellingPriceText =
    scenarioSellingPrices[scenarioPriceKey] ??
    calculations.sellingPricePerPiece.toFixed(2)
  const parsedScenarioSellingPrice = scenarioSellingPriceText.trim() === ""
    ? null
    : parseLocalizedNumber(scenarioSellingPriceText)
  const scenarioSellingPriceIsValid =
    parsedScenarioSellingPrice !== null &&
    Number.isFinite(parsedScenarioSellingPrice) &&
    parsedScenarioSellingPrice >= 0
  const scenarioQuantity = Math.max(1, Number(position.quantity) || 1)
  const scenarioProfitPerPiece = scenarioSellingPriceIsValid
    ? (parsedScenarioSellingPrice as number) - calculations.totalCostPerPiece
    : 0
  const scenarioProfitForSeries = scenarioProfitPerPiece * scenarioQuantity
  const scenarioPriceDeltaPerPiece = scenarioSellingPriceIsValid
    ? (parsedScenarioSellingPrice as number) - calculations.sellingPricePerPiece
    : 0
  const scenarioQuoteProfit = liveQuoteProfitability.totalProfit +
    scenarioPriceDeltaPerPiece * scenarioQuantity

  // Poredi trenutnu prodajnu cijenu s posljednjom ranijom ponudom.
  // Ovo je samo informacija za kalkulanta; ne mijenja kalkulaciju.
  const previousPriceComparison = (() => {
    if (!previousQuotePrice) return null

    const currentPrice = Number(calculations.sellingPricePerPiece)
    const previousPrice = Number(previousQuotePrice.pricePerPiece)

    if (
      !Number.isFinite(currentPrice) ||
      !Number.isFinite(previousPrice) ||
      currentPrice <= 0 ||
      previousPrice <= 0
    ) {
      return null
    }

    const difference = currentPrice - previousPrice
    const percentage = (difference / previousPrice) * 100
    const direction = Math.abs(difference) < 0.005
      ? "same"
      : difference > 0
        ? "higher"
        : "lower"

    return {
      currentPrice,
      previousPrice,
      difference: direction === "same" ? 0 : difference,
      percentage: direction === "same" ? 0 : percentage,
      direction,
    } as const
  })()

  /*
   * ==========================================
   * PROMJENA MARŽE
   * ==========================================
   */

  const handleMarginChange = (
    value: number,
  ) => {
    if (Number.isNaN(value)) {
      return
    }

    const safeValue =
      Math.min(
        200,
        Math.max(0, value),
      )

    updatePosition({ marginPercent: safeValue })
  }

  /*
   * ==========================================
   * SPREMANJE KOMPLETNE PONUDE
   * ==========================================
   */

/*
 * ==========================================
 * SPREMANJE KOMPLETNE PONUDE
 * ==========================================
 *
 * Ovdje:
 *
 * 1. Provjeravamo pozicije
 * 2. Provjeravamo da li je kupac odabran
 * 3. Pronalazimo naziv kupca
 * 4. Pripremamo sve pozicije za Firebase
 * 5. Izračunavamo ukupni trošak
 * 6. Izračunavamo ukupnu prodajnu vrijednost
 * 7. Spremamo ponudu zajedno sa kupcem
 *
 */

/*
 * ==========================================
 * SPREMANJE KOMPLETNE PONUDE
 * ==========================================
 *
 * Ovdje spremamo:
 *
 * - broj ponude
 * - datum
 * - status
 * - kupca
 * - maržu
 * - pozicije
 * - ukupni trošak
 * - prodajnu vrijednost
 * - korisnika koji je kreirao ponudu
 *
 * Kupac se sprema u dva podatka:
 *
 * kupacId    = Firebase ID kupca
 * kupacNaziv = naziv kupca
 *
 * Na taj način kasnije u "Pretraži ponude"
 * možemo jednostavno prikazati naziv kupca.
 *
 * ==========================================
 */

const saveQuote = async (silent = false): Promise<Quote | null> => {
  // Vrijeme se bilježi na korisnikov klik; ne uključujemo čekanje Firebase odgovora.
  const saveActionAt = new Date()

  try {
    /*
     * ==========================================
     * PROVJERA POZICIJA
     * ==========================================
     */

    if (positions.length === 0) {
      alert("Ponuda nema nijednu poziciju.")
      return null
    }

    /*
     * Svaka pozicija mora imati šifru.
     */

    const invalidPosition = positions.find(
      (item) => !item.code.trim(),
    )

    if (invalidPosition) {
      alert(
        "Svaka pozicija mora imati šifru.",
      )
      return null
    }

    const invalidMaterialInput = positions.find((item) => {
      const manuallyEnteredWeight = Math.max(0, Number(item.rawWeightKg) || 0)
      if (manuallyEnteredWeight > 0) return false

      const geometryWarning = getShapeDimensionWarning(item)
      const volumeMm3 = calculateStockVolumeMm3(item)
      return Boolean(geometryWarning) || volumeMm3 <= 0
    })

    if (invalidMaterialInput) {
      alert(`Za poziciju ${invalidMaterialInput.positionNumber} (${invalidMaterialInput.code}) unesi ili kompletne dimenzije sirovine ili ručnu težinu sirovog komada [kg]. Jedno od toga je obavezno.`)
      return null
    }

    if (staffelPricesEnabled) {
      if (staffelMode === "shared") {
        if (staffelQuantityInputInvalid || staffelQuantities.length === 0) {
          alert("Unesi ispravne pozitivne cijele količine za zajedničke Staffelpreise, razdvojene zarezom ili tačka-zarezom.")
          return null
        }
        if (staffelQuantities.length > 30) {
          alert("Za jednu ponudu možeš unijeti najviše 30 različitih Staffel količina.")
          return null
        }
      } else {
        const invalidPositionScale = positions.find((item) => {
          const text = String(item.staffelQuantityText || "").trim()
          return text && (hasInvalidStaffelQuantity(text) || parseStaffelQuantities(text).length > 30)
        })
        const hasAnyPositionScale = positions.some((item) => parseStaffelQuantities(item.staffelQuantityText || "").length > 0)
        if (invalidPositionScale) {
          alert(`Neispravne dodatne količine za poziciju ${invalidPositionScale.positionNumber}. Koristi pozitivne cijele brojeve, razdvojene zarezom ili tačka-zarezom; najviše 30 po poziciji.`)
          return null
        }
        if (!hasAnyPositionScale) {
          alert("Unesi barem jednu dodatnu količinu u kolonu 'Price Scale / Dodatne količine' za neku poziciju.")
          return null
        }
      }
    }

    /*
     * ==========================================
     * PROVJERA KUPCA
     * ==========================================
     *
     * Ponudu sada želimo povezati sa kupcem.
     *
     * Zato ne dozvoljavamo spremanje ponude
     * ako kupac nije odabran.
     */

    if (!selectedCustomerId) {
      alert(
        "Molimo odaberite kupca prije spremanja ponude.",
      )
      return null
    }

    /*
     * Pronalazimo kompletan objekt kupca
     * prema njegovom Firebase ID-u.
     */

    const selectedCustomer =
      customers.find(
        (customer) =>
          customer.id ===
          selectedCustomerId,
      )

    /*
     * Sigurnosna provjera.
     *
     * Ovo se može dogoditi ako je kupac
     * obrisan ili više nije učitan.
     */

    if (
      !selectedCustomer ||
      !selectedCustomer.id
    ) {
      alert(
        "Odabrani kupac nije pronađen.",
      )
      return null
    }

    /*
     * ==========================================
     * BROJ PONUDE
     * ==========================================
     */

    const existingQuoteId = savedQuoteId || editQuoteId
    const now = new Date()

    const brojPonude =
      editingQuote?.brojPonude ||
      `P-${now.getFullYear()}-${String(
        now.getMonth() + 1,
      ).padStart(2, "0")}-${String(
        now.getDate(),
      ).padStart(2, "0")}-${String(
        now.getHours(),
      ).padStart(2, "0")}${String(
        now.getMinutes(),
      ).padStart(2, "0")}${String(
        now.getSeconds(),
      ).padStart(2, "0")}`

    /*
     * ==========================================
     * PRIPREMA POZICIJA
     * ==========================================
     */

    const quotePositions =
      positions.map(
        (item) => {
          /*
           * Pronalazimo površinsku zaštitu
           * odabranu na poziciji.
           */

          const protection =
            surfaceProtections.find(
              (surface) =>
                surface.id ===
                item.surfaceProtectionId,
            )

          /*
           * Pronalazimo termičku obradu
           * odabranu na poziciji.
           */

          const treatment =
            heatTreatments.find(
              (heat) =>
                heat.id ===
                item.heatTreatmentId,
            )

          const otherOperation = otherOperations.find(
            (operation) => operation.id === item.otherOperationId,
          )

          const itemCalculations =
            calculatePositionCostsForSave(
              item,
              marginPercent,
              surfaceProtections,
              heatTreatments,
              otherOperations,
              generalSettings,
              materials,
              machines,
              transportIncluded,
            )

          // Snapshot ulaznih cijena materijala u trenutku izrade kalkulacije.
          // Ove vrijednosti ostaju sačuvane uz ponudu i nakon promjene baze materijala.
          const materialForSnapshot =
            materials[item.material] ||
            defaultMaterials[item.material] ||
            defaultMaterials.S235

          const pricingSnapshot = {
            version: 1,
            capturedAt: now,
            currency: "EUR",
            programmingHourlyRate: itemCalculations.machineProgrammingRate,
            setupHourlyRate: itemCalculations.machineSetupRate,
            machiningHourlyRate: itemCalculations.machineMachiningRate,
            transportPricePerKg: Number.isFinite(Number(item.transportPricePerKg))
              ? Math.max(0, Number(item.transportPricePerKg))
              : Number(generalSettings.transportPricePerKg) || 0,
            transportIncluded: typeof item.transportIncluded === "boolean"
              ? item.transportIncluded
              : transportIncluded,
            ensatPricePerPiece: Number(generalSettings.ensatPricePerPiece) || 0,
            inspectionCostSeries: Number(item.inspectionCostSeries) || 0,
            rawWeightKg: Number(item.rawWeightKg) || 0,
            rawWeightUsedKg: itemCalculations.weightKg,
            outputWeightKg: Number(item.outputWeightKg) || 0,
            outputWeightUsedKg: itemCalculations.outputWeightKg,
            marginPercent: itemCalculations.safeMarginPercent,
          }

          /*
           * Vraćamo kompletnu poziciju
           * koja će biti spremljena u ponudu.
           */

          return {
            positionNumber:
              item.positionNumber,

            code:
              item.code,

            name:
              item.name,

            quantity:
              item.quantity,

            transportIncluded: typeof item.transportIncluded === "boolean"
              ? item.transportIncluded
              : transportIncluded,
            transportPricePerKg: Number.isFinite(Number(item.transportPricePerKg))
              ? Math.max(0, Number(item.transportPricePerKg))
              : Number(generalSettings.transportPricePerKg) || 0,

            shape:
              item.shape,

            length:
              item.length,

            width:
              item.width,

            height:
              item.height,

            diameter:
              item.diameter,
            rawWeightKg:
              Number(item.rawWeightKg) || 0,
            thickness:
              item.thickness,
            flangeThickness:
              item.flangeThickness,
            webThickness:
              item.webThickness,
            customCrossSectionArea:
              item.customCrossSectionArea,

            material:
              item.material,

            // Cijena i gustina materijala kakve su korištene pri ovoj kalkulaciji.
            materialSnapshot: {
              code: item.material,
              name: String(materialForSnapshot.name || item.material),
              pricePerKg: Math.max(0, Number(materialForSnapshot.pricePerKg) || 0),
              densityKgPerM3: Math.max(0, Number(materialForSnapshot.density) || 0),
            },

            // Sačuvane pretpostavke obračuna; ne mijenjaju se naknadno s bazom cijena.
            pricingSnapshot,

            programmingMinutes:
              item.programmingMinutes,

            setupMinutes:
              item.setupMinutes,

            machiningMinutes:
              item.machiningMinutes,

            machine:
              item.machine,

            /*
             * Površinska zaštita
             */

            surfaceProtectionId:
              item.surfaceProtectionId,

            surfaceProtectionName:
              item.surfaceProtectionName,

            /*
              * ==========================================
              * POVRŠINSKA ZAŠTITA
              * ==========================================
              *
              * Firebase Firestore ne dozvoljava
              * vrijednost undefined.
              *
              * Zato sva opcionalna polja pretvaramo
              * u prazan string ako nisu unesena.
              */

              surfaceProtection: protection
                ? {
                    id: protection.id ?? "",
                    naziv: protection.naziv ?? "",
                    cijena: Number(protection.cijena) || 0,
                    tipCijene: protection.tipCijene ?? "",
                    napomena: protection.napomena ?? "",
                  }
                : null,

            /*
             * Termička obrada
             */

            heatTreatmentId:
              item.heatTreatmentId,

            heatTreatmentName:
              item.heatTreatmentName,

            /*
              * ==========================================
              * TERMIČKA OBRADA
              * ==========================================
              *
              * I ovdje osiguravamo da nijedno polje
              * ne može biti undefined.
              */

              heatTreatment: treatment
                ? {
                    id: treatment.id ?? "",
                    naziv: treatment.naziv ?? "",
                    cijena: Number(treatment.cijena) || 0,
                    tipCijene: treatment.tipCijene ?? "",
                    napomena: treatment.napomena ?? "",
                  }
                : null,

            otherOperationId: item.otherOperationId || "",
            otherOperationName: item.otherOperationName || "",
            otherOperation: otherOperation
              ? {
                  id: otherOperation.id ?? "",
                  naziv: otherOperation.naziv ?? "",
                  cijena: Number(otherOperation.cijena) || 0,
                  tipCijene: otherOperation.tipCijene ?? "komad",
                  napomena: otherOperation.napomena ?? "",
                }
              : null,

            otherCost1Description: item.otherCost1Description || "",
            otherCost1Amount: Number(item.otherCost1Amount) || 0,
            otherCost2Description: item.otherCost2Description || "",
            otherCost2Amount: Number(item.otherCost2Amount) || 0,
            ensatCount: Number(item.ensatCount) || 0,
            ensatPricePerPiece: Number(generalSettings.ensatPricePerPiece) || 0,

            /*
             * Troškovi
             */

            crossSectionAreaMm2:
              itemCalculations.crossSectionAreaMm2,

            // rawWeightKg je već sačuvan ranije u ovom objektu.
            // Ovdje čuvamo izračunatu težinu koja je korištena u kalkulaciji.
            rawWeightUsedKg:
              itemCalculations.weightKg,
            weightKg:
              itemCalculations.weightKg,

            // 0 u outputWeightKg znači da nije ručno unesena izlazna težina;
            // outputWeightUsedKg bilježi vrijednost korištenu u kalkulaciji.
            outputWeightKg:
              Number(item.outputWeightKg) || 0,

            outputWeightUsedKg:
              itemCalculations.outputWeightKg,

            materialCost:
              itemCalculations.materialCost,

            programmingCost:
              itemCalculations.programmingCost,

            setupCost:
              itemCalculations.setupCost,

            machiningCost:
              itemCalculations.machiningCost,

            inspectionCostSeries:
              Number(item.inspectionCostSeries) || 0,

            inspectionCost:
              itemCalculations.inspectionCost,

            // Prije je transport ulazio u ukupan trošak, ali njegova stavka
            // nije bila posebno sačuvana uz poziciju.
            transportCost:
              itemCalculations.transportCost,

            surfaceProtectionCost:
              itemCalculations.surfaceProtectionCost,

            heatTreatmentCost:
              itemCalculations.heatTreatmentCost,

            otherOperationCost:
              itemCalculations.otherOperationCost,
            otherCostsTotal:
              itemCalculations.otherCostsTotal,
            ensatCostPerPiece:
              itemCalculations.ensatCostPerPiece,

            totalCostPerPiece:
              itemCalculations.totalCostPerPiece,

            totalCost:
              itemCalculations.totalCost,

            machineRate:
              itemCalculations.machineRate,

            machineSetupRate:
              itemCalculations.machineSetupRate,

            machineMachiningRate:
              itemCalculations.machineMachiningRate,

            machineProgrammingRate:
              itemCalculations.machineProgrammingRate,
            programmingHourlyRate:
              itemCalculations.machineProgrammingRate,

            machineName:
              machines[item.machine]?.name || item.machine,

            /*
             * Marža
             */

            marginPercent:
              itemCalculations.safeMarginPercent,

            marginAmount:
              itemCalculations.marginAmount,

            marginValuePerPiece:
              itemCalculations.marginValuePerPiece,

            /*
             * Prodajna cijena
             */

            sellingPricePerPiece:
              itemCalculations.sellingPricePerPiece,

            totalSellingPrice:
              itemCalculations.totalSellingPrice,

            // Staffelpreise su alternativni cjenovni nivoi. U zajedničkom modu
            // koristi se jedna lista za sve pozicije; u pojedinačnom modu svaka
            // pozicija čuva vlastitu listu iz Price Scale kolone.
            staffelKolicine: staffelPricesEnabled
              ? (staffelMode === "shared" ? staffelQuantities : parseStaffelQuantities(item.staffelQuantityText || ""))
              : [],
            staffelpreise: staffelPricesEnabled
              ? (staffelMode === "shared" ? staffelQuantities : parseStaffelQuantities(item.staffelQuantityText || "")).map((tierQuantity) => {
                  const tier = calculatePositionCostsForSave(
                    { ...item, quantity: tierQuantity },
                    marginPercent,
                    surfaceProtections,
                    heatTreatments,
                    otherOperations,
                    generalSettings,
                    materials,
                    machines,
                    transportIncluded,
                  )
                  return {
                    quantity: tierQuantity,
                    totalCostPerPiece: tier.totalCostPerPiece,
                    totalCost: tier.totalCost,
                    sellingPricePerPiece: tier.sellingPricePerPiece,
                    totalSellingPrice: tier.totalSellingPrice,
                    marginValuePerPiece: tier.marginValuePerPiece,
                  }
                })
              : [],
          }
        },
      )

    /*
     * ==========================================
     * UKUPNI TROŠAK
     * ==========================================
     */

    const totalCost =
      quotePositions.reduce(
        (sum, item) =>
          sum + item.totalCost,
        0,
      )

    /*
     * ==========================================
     * UKUPNA PRODAJNA VRIJEDNOST
     * ==========================================
     */

    const totalSellingPrice =
      quotePositions.reduce(
        (sum, item) =>
          sum +
          item.totalSellingPrice,
        0,
      )


      /*
 * ==========================================
 * KUPAC
 * ==========================================
 *
 * Pronalazimo trenutno odabranog kupca
 * prema njegovom ID-u.
 */


/*
 * ==========================================
 * PROVJERA KUPCA
 * ==========================================
 *
 * Kupac mora biti odabran prije spremanja
 * ponude.
 */
if (
  !selectedCustomerId ||
  !selectedCustomer
) {
  alert(
    "Molimo odaberite kupca prije spremanja ponude.",
  )

  return null
}

          /*
          * ==========================================
          * SPREMANJE PONUDE
          * ==========================================
          *
          * Ovdje sada spremamo:
          *
          * - kupacId    = Firebase ID kupca
          * - kupacNaziv = naziv kupca
          *
          * Tako će kupac ostati trajno povezan
          * sa ovom ponudom.
          */
          // ==========================================
          // AUTOR PONUDE
          // ==========================================
          // Kod nove ponude čitamo ime prijavljenog korisnika
          // iz njegovog Firestore profila. Pri uređivanju čuvamo
          // originalnog autora i ne mijenjamo historiju ponude.
          let createdBy = editingQuote?.createdBy?.trim() || ""

          if (!existingQuoteId) {
            if (!authProfile) {
              alert(
                "Nije moguće utvrditi prijavljenog korisnika. Prijavite se ponovo prije spremanja ponude.",
              )
              return null
            }

            createdBy = String(
              authProfile.imePrezime || authProfile.email || "",
            ).trim()

            if (!createdBy) {
              alert(
                "Nije pronađeno ime prijavljenog korisnika. Provjerite korisnički profil.",
              )
              return null
            }
          }

          // Vrijeme se prvi put zaključava na uspješno spremanje/izvoz nove ponude.
          // Pri kasnijem uređivanju čuvamo originalno izmjereno vrijeme.
          const quotePreparationSnapshot =
            !existingQuoteId &&
            isNewQuoteTimerSessionRef.current &&
            !quotePreparationTimerFinalizedRef.current &&
            quotePreparationStartedAtRef.current &&
            quotePreparationStartedAtMsRef.current !== null
              ? {
                  startedAt: quotePreparationStartedAtRef.current,
                  endedAt: saveActionAt,
                  durationMs: Math.max(
                    0,
                    saveActionAt.getTime() - quotePreparationStartedAtMsRef.current,
                  ),
                }
              : null

          const quotePreparationFields = existingQuoteId
            ? {
                ...(editingQuote?.vrijemeIzradePonudePocetak !== undefined
                  ? { vrijemeIzradePonudePocetak: editingQuote.vrijemeIzradePonudePocetak }
                  : {}),
                ...(editingQuote?.vrijemeIzradePonudeKraj !== undefined
                  ? { vrijemeIzradePonudeKraj: editingQuote.vrijemeIzradePonudeKraj }
                  : {}),
                ...(editingQuote?.vrijemeIzradePonudeMs !== undefined
                  ? { vrijemeIzradePonudeMs: editingQuote.vrijemeIzradePonudeMs }
                  : {}),
              }
            : quotePreparationSnapshot
              ? {
                  vrijemeIzradePonudePocetak: quotePreparationSnapshot.startedAt,
                  vrijemeIzradePonudeKraj: quotePreparationSnapshot.endedAt,
                  vrijemeIzradePonudeMs: quotePreparationSnapshot.durationMs,
                }
              : {}

          const normalizedCustomerInquiryNumber = customerInquiryNumber.trim()
          const customerInquiryNumberData = normalizedCustomerInquiryNumber
            ? { brojUpitaKupca: normalizedCustomerInquiryNumber }
            : existingQuoteId
              ? { brojUpitaKupca: "" }
              : {}

          const quoteData = {
            brojPonude,
            ...customerInquiryNumberData,
            datum: editingQuote?.datum || now,
            status: editingQuote?.status || "draft",
            marginPercent,
            transportIncluded,
            positions: quotePositions,
            totalCost,
            totalSellingPrice,
            // Verzija obračuna i globalne pretpostavke korištene pri spremanju ponude.
            // Pojedinačne cijene materijala i satnice mašina sačuvane su uz svaku poziciju.
            calculationSnapshot: {
              version: 1,
              capturedAt: now,
              currency: "EUR",
              programmingRateModel: "per-machine",
              transportPricePerKg: Number(generalSettings.transportPricePerKg) || 0,
              transportIncluded,
              ensatPricePerPiece: Number(generalSettings.ensatPricePerPiece) || 0,
              marginPercent: Math.min(200, Math.max(0, Number(marginPercent) || 0)),
              positionCount: quotePositions.length,
              totalCost,
              totalSellingPrice,
            },
            staffelpreiseAktivno: staffelPricesEnabled,
            staffelNacin: staffelMode,
            staffelKolicine: staffelPricesEnabled && staffelMode === "shared" ? staffelQuantities : [],
            createdBy: editingQuote?.createdBy || createdBy || "Nepoznat korisnik",
            ...quotePreparationFields,

            // ========================================
            // PODACI O KUPCU
            // ========================================
            kupacId:
              selectedCustomer.id ?? "",

            kupacNaziv:
              selectedCustomer.naziv ?? "",
          }

          let quoteSuccessMessage: string
          let savedQuote: Quote

          if (existingQuoteId) {
            const updatedQuote = await updateQuote(
              existingQuoteId,
              quoteData,
            )

            savedQuote = {
              ...quoteData,
              ...updatedQuote,
              id: existingQuoteId,
            } as Quote

            quoteSuccessMessage =
              `Ponuda ${brojPonude} za kupca ${selectedCustomer.naziv} je uspješno ažurirana.`
          } else {
            const createdQuote = await addQuote(quoteData)
            savedQuote = createdQuote as Quote

            // Nakon prvog spremanja svaka nova ponuda dobija edit ID u URL-u.
            // Refresh zato otvara već spremljenu ponudu umjesto prazne forme/duplikata.
            if (createdQuote.id) {
              const savedQuoteUrl = new URL(window.location.href)
              savedQuoteUrl.searchParams.delete("copy")
              savedQuoteUrl.searchParams.delete("new")
              savedQuoteUrl.searchParams.set("edit", createdQuote.id)
              window.history.replaceState(
                {},
                "",
                `${savedQuoteUrl.pathname}${savedQuoteUrl.search}${savedQuoteUrl.hash}`,
              )
            }

            quoteSuccessMessage =
              `Ponuda ${brojPonude} za kupca ${selectedCustomer.naziv} je uspješno sačuvana.`
          }

          // Nakon uspješnog prvog spremanja zaključavamo vrijeme izrade.
          // Naknadne izmjene iste ponude više ne mijenjaju originalno vrijeme.
          if (quotePreparationSnapshot) {
            quotePreparationTimerFinalizedRef.current = true
            setQuotePreparationElapsedMs(quotePreparationSnapshot.durationMs)
            setQuotePreparationTimerFinished(true)
            try {
              window.sessionStorage.removeItem(QUOTE_PREPARATION_TIMER_STORAGE_KEY)
            } catch {
              // Čuvanje u Firestoreu je već uspješno; čišćenje sessionStorage nije obavezno.
            }
          }

          // Nakon prvog spremanja zapamtimo ID i podatke ponude.
          // Tako i sljedeći izvoz radi ažuriranje iste ponude.
          setSavedQuoteId(savedQuote.id || existingQuoteId || null)
          setEditingQuote(savedQuote)
          await clearCurrentQuoteDraftAfterSave()

          // Pozicije se automatski ažuriraju u bazi tek nakon uspješnog
          // spremanja ponude. Jedinstvene šifre obrađujemo samo jednom.
          try {
            const positionsByCode = new Map<string, Position>();

            for (const item of positions) {
              const code = normalizePositionLookupCode(item.code);
              if (code) {
                positionsByCode.set(code, item);
              }
            }

            const positionsToSync = Array.from(positionsByCode.entries())
              .filter(([code]) => !skipPositionDatabaseSyncCodes[code])
              .map(([, item]) => item)

            await Promise.all(
              positionsToSync.map((item) => upsertPositionInDatabase(item)),
            );

            const protectedCount = Array.from(positionsByCode.keys())
              .filter((code) => skipPositionDatabaseSyncCodes[code]).length
            if (!silent) {
              alert(
                quoteSuccessMessage +
                (protectedCount > 0
                  ? `\n\nNapomena: ${protectedCount} postojećih zapisa pozicija nije prepisano jer je u provjeri odabrano da se tehnički podaci iz baze ne koriste.`
                  : ""),
              )
            }
          } catch (positionSaveError) {
            console.error(
              "Ponuda je sačuvana, ali sinhronizacija baze pozicija nije uspjela:",
              positionSaveError,
            );

            alert(
              `${quoteSuccessMessage}\n\nUpozorenje: ponuda je sačuvana, ali nije uspjelo spremanje svih pozicija u bazu pozicija. Provjeri konzolu za detalje.`,
            );
          }

          return savedQuote

  /*
 * ==========================================
 * PRIKAZ STVARNE GREŠKE
 * ==========================================
 *
 * Trenutno smo imali samo opću poruku.
 * Ovaj dio će nam pokazati šta Firebase
 * stvarno odbija.
 */

  } catch (error: any) {

  // Ispis kompletne greške u browser konzolu
  console.error(
    "GREŠKA PRI SPREMANJU PONUDE:",
    error,
  )

  // Firebase često ima posebno polje "code"
  const errorCode =
    error?.code || "nepoznat kod"

  // Poruka greške
  const errorMessage =
    error?.message ||
    String(error)

  /*
   * Prikazujemo stvarni razlog greške.
   *
   * Ovo nam omogućava da razlikujemo:
   *
   * - permission-denied
   * - unsupported field value
   * - network error
   * - pogrešan Firebase path
   * - problem sa podacima
   */

  alert(
    `Greška pri spremanju ponude.\n\n` +
    `Kod: ${errorCode}\n\n` +
    `Poruka:\n${errorMessage}`,
  )

  return null
  }
}


  const handleExportExcel = async () => {
    if (exportingFormat) return

    setExportingFormat("excel")
    try {
      const savedQuote = await saveQuote(true)
      if (!savedQuote) return

      exportQuotePositionsToExcel(savedQuote)
    } catch (error) {
      console.error("Greška pri izvozu ponude u Excel:", error)
      alert("Ponuda je sačuvana, ali izvoz u Excel nije uspio.")
    } finally {
      setExportingFormat(null)
    }
  }

  const handleExportPdf = async () => {
    if (exportingFormat) return

    // Prozor otvaramo direktno na klik kako pregled PDF-a ne bi blokirao browser.
    const printWindow = window.open("", "_blank", "width=1050,height=800")
    if (!printWindow) {
      alert("Pregled za PDF je blokiran. Dozvoli iskačuće prozore za ovu aplikaciju i pokušaj ponovo.")
      return
    }

    printWindow.document.write("<!doctype html><html lang='bs'><body style='font-family:Arial;padding:24px'>Spremanje ponude prije izvoza...</body></html>")
    printWindow.document.close()

    setExportingFormat("pdf")
    try {
      const savedQuote = await saveQuote(true)
      if (!savedQuote) {
        printWindow.close()
        return
      }

      writeQuotePdf(printWindow, savedQuote)
    } catch (error) {
      console.error("Greška pri izvozu ponude u PDF:", error)
      printWindow.close()
      alert("Ponuda je sačuvana, ali izvoz u PDF nije uspio.")
    } finally {
      setExportingFormat(null)
    }
  }

  const openTgExportModal = () => {
    if (exportingFormat) return
    setTgMarkupPercentText("")
    setTgExportError("")
    setShowTgExportModal(true)
  }

  const handleExportPdfTg = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (exportingFormat) return

    const trimmedMarkup = tgMarkupPercentText.trim()
    const markupPercent = trimmedMarkup === "" ? null : parseLocalizedNumber(trimmedMarkup)
    if (markupPercent === null || !Number.isFinite(markupPercent) || markupPercent < 0) {
      setTgExportError("Unesi ispravnu maržu od 0% ili više. Možeš koristiti decimalni zarez.")
      return
    }

    // Prozor otvaramo prije prvog await-a kako pregled PDF-a ne bi blokirao browser.
    const printWindow = window.open("", "_blank", "width=1050,height=800")
    if (!printWindow) {
      setTgExportError("Pregled PDF-a je blokiran. Dozvoli iskačuće prozore za ovu aplikaciju i pokušaj ponovo.")
      return
    }

    printWindow.document.write("<!doctype html><html lang='bs'><body style='font-family:Arial;padding:24px'>Priprema PDF TG dokumenta...</body></html>")
    printWindow.document.close()

    setShowTgExportModal(false)
    setExportingFormat("pdf")
    try {
      // Sačuvamo aktuelne izmjene standardnom logikom. TG cijene se primjenjuju
      // samo na kopiju vraćenog objekta i nikada se ne upisuju u ponudu u bazi.
      const savedQuote = await saveQuote(true)
      if (!savedQuote) {
        printWindow.close()
        return
      }

      const tgQuote = applyAdditionalMarkupForPdfTg(savedQuote, markupPercent)
      const tgCustomer = customers.find((customer) => customer.id === selectedCustomerId)
      writeTgQuotePdf(printWindow, tgQuote, tgCustomer)
    } catch (error) {
      console.error("Greška pri izvozu ponude u PDF TG:", error)
      printWindow.close()
      alert("Ponuda je sačuvana, ali izvoz u PDF TG nije uspio.")
    } finally {
      setExportingFormat(null)
    }
  }

  return (
    <div className="new-quote">

      {!draftHydrated && (
        <div className="quote-draft-loading-backdrop" role="status">
          <div className="quote-draft-loading-card">
            <span className="quote-draft-loading-icon" aria-hidden="true">⟳</span>
            <strong>Provjeravam radnu ponudu...</strong>
            <span>Vraćam posljednje automatski sačuvane podatke.</span>
          </div>
        </div>
      )}

      {showDraftRecovery && draftRecoveryItems.length > 0 && (
        <div className="quote-draft-recovery-backdrop" role="presentation">
          <section className="quote-draft-recovery-modal" role="dialog" aria-modal="true" aria-labelledby="quote-draft-recovery-title">
            <div className="quote-draft-recovery-heading">
              <span className="quote-draft-recovery-icon" aria-hidden="true">↶</span>
              <div>
                <h2 id="quote-draft-recovery-title">Pronađene su nedovršene ponude</h2>
                <p>Možeš nastaviti neku raniju kalkulaciju ili započeti novu. Trenutni unos još nije započet.</p>
              </div>
            </div>
            <div className="quote-draft-recovery-list">
              {draftRecoveryItems.map((draft) => (
                <article className="quote-draft-recovery-item" key={draft.draftId}>
                  <div>
                    <strong>{draft.label || "Nedovršena ponuda"}</strong>
                    <span>{draft.positionCount} pozicija · {new Date(draft.updatedAtMs).toLocaleString("bs-BA")}</span>
                  </div>
                  <div className="quote-draft-recovery-item-actions">
                    <button type="button" className="primary-button" onClick={() => resumeQuoteDraft(draft)}>
                      Nastavi
                    </button>
                    <button type="button" className="secondary-button" onClick={() => void discardRecoveryDraft(draft)}>
                      Obriši
                    </button>
                  </div>
                </article>
              ))}
            </div>
            <div className="quote-draft-recovery-actions">
              <button type="button" className="secondary-button" onClick={startAnotherQuoteDraft}>
                Započni novu ponudu
              </button>
            </div>
          </section>
        </div>
      )}

      {showTgExportModal && (
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
              <p className="quote-tg-modal-note">Ovaj dodatak važi samo za PDF TG. Standardna ponuda i cijene spremljene u bazi ostaju nepromijenjene.</p>
              {tgExportError && <p className="quote-tg-modal-error" role="alert">{tgExportError}</p>}
              <div className="quote-tg-modal-actions">
                <button type="button" className="secondary-button" onClick={() => setShowTgExportModal(false)}>Odustani</button>
                <button type="submit" className="primary-button">Generiši PDF TG</button>
              </div>
            </form>
          </section>
        </div>
      )}

      {historyPrefillNotice && (
        <div role="status" style={{ marginBottom: 16, padding: "12px 15px", border: "1px solid #bfdbfe", borderRadius: 10, background: "#eff6ff", color: "#1e40af", fontSize: 13, lineHeight: 1.55 }}>
          <strong>Historijska kalkulacija je učitana.</strong> {historyPrefillNotice}
        </div>
      )}

      {importPreview && (
        <div className="inquiry-preview-backdrop" role="presentation">
          <section
            className="inquiry-preview-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="inquiry-preview-title"
          >
            <div className="inquiry-preview-header">
              <div>
                <h2 id="inquiry-preview-title">Provjera uvezenog upita</h2>
                <p>Datoteka: <strong>{importSourceName}</strong>. Provjeri šifre, nazive i količine prije primjene.</p>
              </div>
              <button type="button" className="secondary-button" onClick={() => setImportPreview(null)} disabled={importingInquiry}>
                Zatvori
              </button>
            </div>

            {importInfoMessage && (
              <div className="inquiry-import-info" role="status">
                {importInfoMessage}
              </div>
            )}

            {customerRecognitionMessage && (
              <div className="inquiry-import-info customer-recognition-info" role="status">
                <div>{customerRecognitionMessage}</div>
                {customerRecognitionSuggestions.length > 0 && (
                  <div style={{ display: "flex", flexWrap: "wrap", gap: 8, marginTop: 10 }}>
                    {customerRecognitionSuggestions.map((customer) => (
                      <button
                        key={customer.id || customer.naziv}
                        type="button"
                        className="secondary-button"
                        onClick={() => chooseRecognizedCustomer(customer)}
                        disabled={!customer.id}
                      >
                        Odaberi: {customer.naziv}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}

            <div className={`inquiry-preview-notice ${importValidation.blockingCount > 0 ? "has-errors" : importValidation.missingNameCount > 0 || importValidation.duplicateCodeCount > 0 ? "has-warnings" : ""}`}>
              {importPreview.length === 0
                ? "Automatsko prepoznavanje nije pronašlo pozicije. Ako je ovo Excel upit, otvori ručno mapiranje kolona ispod."
                : `Prepoznato je ${importPreview.length} pozicija. `}
              {importPreview.length > 0 && <>
                {importValidation.missingCodeCount > 0 && `${importValidation.missingCodeCount} redova nema šifru. `}
                {importValidation.invalidQuantityCount > 0 && `${importValidation.invalidQuantityCount} redova ima neispravnu količinu. `}
                {importPreview.filter((row) => String(row.staffelQuantityText || "").trim() && (hasInvalidStaffelQuantity(String(row.staffelQuantityText)) || parseStaffelQuantities(String(row.staffelQuantityText)).length > 30)).length > 0 && "Neki Price Scale unosi nisu ispravni. "}
                {importValidation.missingNameCount > 0 && `${importValidation.missingNameCount} redova nema naziv. `}
                {importValidation.duplicateCodeCount > 0 && `${importValidation.duplicateCodeCount} redova ima šifru koja se ponavlja. `}
                {importValidation.blockingCount > 0
                  ? "Ispravi obavezne greške prije uvoza."
                  : "Provjeri označena upozorenja. Duplikati nisu automatski uklonjeni jer mogu biti namjerne pozicije."}
              </>}
              {" "}Uvoz zamjenjuje trenutni spisak pozicija tek nakon potvrde. Za PDF je obavezna ručna potvrda; skenirani PDF bez tekstualnog sloja zahtijeva OCR.
            </div>

            {importPreview.length > 0 && (
              <section className="inquiry-position-check-panel">
                <div className="inquiry-position-check-heading">
                  <div>
                    <strong>Provjera postojećih pozicija</strong>
                    <p>Provjeri šifre u bazi prije nastavka. Kod razlike u nazivu tehnički podaci se neće automatski preuzeti dok to ne potvrdiš.</p>
                  </div>
                  <button
                    type="button"
                    className="secondary-button"
                    onClick={() => void checkImportedPositions()}
                    disabled={checkingImportPositions || importingInquiry}
                  >
                    {checkingImportPositions ? "⏳ Provjeravam bazu..." : Object.keys(importPositionChecks).length ? "🔄 Ponovi provjeru baze" : "🔎 Provjeri pozicije u bazi"}
                  </button>
                </div>
                {importPositionCheckSummary.checked > 0 && (
                  <div className="inquiry-position-check-summary" role="status">
                    Provjereno {importPositionCheckSummary.checked} od {importPreview.length}: {importPositionCheckSummary.found} poznatih, {importPositionCheckSummary.newPositions} novih, {importPositionCheckSummary.nameMismatches} razlika u nazivu, {importPositionCheckSummary.errors} grešaka.
                    {importPositionCheckSummary.checked < importPreview.length ? " Promijenjene šifre/nazive treba ponovo provjeriti." : ""}
                  </div>
                )}
              </section>
            )}

            {excelImportGrid && (
              <section className="inquiry-manual-mapping">
                <div className="inquiry-manual-mapping-heading">
                  <div>
                    <strong>Prepoznavanje Excel kolona</strong>
                    <p>Ako nazivi ili količine nisu prepoznati pravilno, odaberi kolone ručno. Ovo ne mijenja originalnu datoteku.</p>
                  </div>
                  <div className="inquiry-manual-mapping-heading-actions">
                    <button
                      type="button"
                      className="secondary-button"
                      onClick={saveExcelImportProfile}
                      disabled={savingImportProfile || !selectedCustomerId || !excelImportGrid}
                      title={!selectedCustomerId ? "Prvo odaberi kupca na vrhu stranice" : "Zapamti kolone i početni red za ovog kupca"}
                    >
                      {savingImportProfile ? "⏳ Spremanje formata..." : "💾 Sačuvaj format za kupca"}
                    </button>
                    <button
                      type="button"
                      className="secondary-button"
                      onClick={() => setShowExcelColumnMapping((current) => !current)}
                      aria-expanded={showExcelColumnMapping}
                    >
                      {showExcelColumnMapping ? "Sakrij mapiranje" : "Podesi kolone ručno"}
                    </button>
                  </div>
                </div>

                {showExcelColumnMapping && (
                  <div className="inquiry-manual-mapping-controls">
                    <label>
                      Prvi red podataka
                      <select value={excelDataStartRow} onChange={(event) => setExcelDataStartRow(Number(event.target.value))}>
                        {excelImportGrid.slice(0, Math.min(excelImportGrid.length, 80)).map((row, index) => (
                          <option key={index} value={index}>
                            {`Red ${index + 1}: ${row.filter((cell) => String(cell ?? "").trim()).join(" | ").slice(0, 110) || "prazan red"}`}
                          </option>
                        ))}
                      </select>
                    </label>

                    {([
                      { key: "positionNumber", label: "Broj pozicije", required: false },
                      { key: "code", label: "Šifra", required: true },
                      { key: "name", label: "Naziv", required: true },
                      { key: "quantity", label: "Količina", required: true },
                      { key: "priceScale", label: "Price Scale / Dodatne količine", required: false },
                    ] as const).map(({ key, label, required }) => (
                      <label key={key}>
                        {label}{required ? " *" : " (opcionalno)"}
                        <select
                          value={excelColumnMapping[key]}
                          onChange={(event) => setExcelColumnMapping((current) => ({ ...current, [key]: Number(event.target.value) }))}
                        >
                          <option value={-1}>— Ne koristi —</option>
                          {Array.from({ length: excelColumnCount }, (_, columnIndex) => {
                            const candidateHeader = String(excelImportGrid[Math.max(0, excelDataStartRow - 1)]?.[columnIndex] ?? "").trim()
                            const firstValue = String(excelImportGrid.find((row) => String(row[columnIndex] ?? "").trim())?.[columnIndex] ?? "").trim()
                            const labelText = candidateHeader || firstValue
                            return (
                              <option key={columnIndex} value={columnIndex}>
                                {`${getExcelColumnLetter(columnIndex)}${labelText ? ` — ${labelText.slice(0, 45)}` : ""}`}
                              </option>
                            )
                          })}
                        </select>
                      </label>
                    ))}

                    <div className="inquiry-manual-mapping-actions">
                      <button type="button" className="primary-button" onClick={applyManualExcelMapping}>
                        Primijeni mapiranje
                      </button>
                    </div>
                  </div>
                )}
              </section>
            )}

            <div className="inquiry-preview-table-wrap">
              <table className="inquiry-preview-table">
                <thead>
                  <tr><th>Br.</th><th>Šifra</th><th>Naziv</th><th>Required Qty</th><th>Price Scale / Dodatne količine</th><th>Provjera</th></tr>
                </thead>
                <tbody>
                  {importPreview.map((row, index) => {
                    const validation = importValidation.rows[index]
                    const databaseCheck = importPositionChecks[index]
                    const rowClass = validation?.blocking
                      ? "inquiry-preview-row-error"
                      : validation?.issues.length || databaseCheck?.nameMismatch || databaseCheck?.status === "error"
                        ? "inquiry-preview-row-warning"
                        : ""
                    return (
                      <tr key={`${index}-${row.positionNumber}`} className={rowClass}>
                        <td>
                          <input
                            aria-label={`Broj pozicije ${index + 1}`}
                            value={row.positionNumber}
                            disabled={checkingImportPositions || importingInquiry}
                            onChange={(event) => updateImportPreviewRow(index, { positionNumber: event.target.value })}
                          />
                        </td>
                        <td>
                          <input
                            aria-label={`Šifra pozicije ${index + 1}`}
                            className={!row.code.trim() ? "inquiry-input-error" : ""}
                            value={row.code}
                            disabled={checkingImportPositions || importingInquiry}
                            onChange={(event) => updateImportPreviewRow(index, { code: event.target.value })}
                          />
                        </td>
                        <td>
                          <input
                            aria-label={`Naziv pozicije ${index + 1}`}
                            className={!row.name.trim() ? "inquiry-input-warning" : ""}
                            value={row.name}
                            disabled={checkingImportPositions || importingInquiry}
                            onChange={(event) => updateImportPreviewRow(index, { name: event.target.value })}
                          />
                        </td>
                        <td>
                          <LocalizedNumberInput
                            aria-label={`Količina pozicije ${index + 1}`}
                            min="0.01"
                            step="any"
                            className={!(Number.isFinite(Number(row.quantity)) && Number(row.quantity) > 0) ? "inquiry-input-error" : ""}
                            value={row.quantity}
                            disabled={checkingImportPositions || importingInquiry}
                            onValueChange={(value) => updateImportPreviewRow(index, { quantity: value })}
                          />
                        </td>
                        <td>
                          <input
                            aria-label={`Price Scale pozicije ${index + 1}`}
                            type="text"
                            className={validation?.issues.some((issue) => issue.includes("Price Scale")) ? "inquiry-input-error" : ""}
                            value={row.staffelQuantityText || ""}
                            placeholder="npr. 1; 2; 4"
                            disabled={checkingImportPositions || importingInquiry}
                            onChange={(event) => updateImportPreviewRow(index, { staffelQuantityText: event.target.value })}
                          />
                        </td>
                        <td>
                          <div className={`inquiry-row-status ${validation?.blocking ? "error" : validation?.issues.length ? "warning" : "ok"}`}>
                            {validation?.issues.length ? validation.issues.join(" · ") : "Podaci iz upita: u redu"}
                          </div>
                          {importPositionChecks[index] && (
                            <div className={`inquiry-row-status inquiry-db-check-status ${
                              importPositionChecks[index].status === "error" || importPositionChecks[index].status === "missing-code"
                                ? "error"
                                : importPositionChecks[index].nameMismatch
                                  ? "warning"
                                  : importPositionChecks[index].status === "new"
                                    ? "new"
                                    : "ok"
                            }`}>
                              {importPositionChecks[index].status === "found" && !importPositionChecks[index].nameMismatch && "✅ Pozicija postoji u bazi"}
                              {importPositionChecks[index].status === "found" && importPositionChecks[index].nameMismatch && "⚠️ Naziv se razlikuje"}
                              {importPositionChecks[index].status === "new" && "🆕 Nova pozicija"}
                              {importPositionChecks[index].status === "error" && "❌ Provjera baze nije uspjela"}
                              {importPositionChecks[index].status === "missing-code" && "❌ Nedostaje šifra"}
                            </div>
                          )}
                          {importPositionChecks[index]?.status === "found" && importPositionChecks[index].databaseName && (
                            <div className="inquiry-db-existing-name">
                              <span>Naziv u bazi: {importPositionChecks[index].databaseName}</span>
                              <label className="inquiry-use-db-data">
                                <input
                                  type="checkbox"
                                  checked={importPositionChecks[index].useDatabaseData}
                                  onChange={(event) => setUseImportDatabaseData(index, event.target.checked)}
                                />
                                Koristi tehničke podatke iz baze
                              </label>
                            </div>
                          )}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>

            {importError && <p className="inquiry-import-error" role="alert">{importError}</p>}

            {importSourceName.toLocaleLowerCase().endsWith(".pdf") && importPreview.length > 0 && (
              <label
                className="inquiry-pdf-confirmation"
                style={{
                  display: "flex",
                  alignItems: "flex-start",
                  gap: 10,
                  margin: "14px 0 4px",
                  padding: "12px 14px",
                  border: "1px solid #bfdbfe",
                  borderRadius: 9,
                  background: "#eff6ff",
                  color: "#1e3a8a",
                  fontSize: 13,
                  lineHeight: 1.5,
                }}
              >
                <input
                  type="checkbox"
                  checked={pdfImportConfirmed}
                  disabled={importingInquiry || checkingImportPositions}
                  onChange={(event) => {
                    setPdfImportConfirmed(event.target.checked)
                    setImportError("")
                  }}
                  style={{ marginTop: 3, flex: "0 0 auto" }}
                />
                <span>
                  <strong>Potvrđujem da sam pregledao podatke iz PDF-a.</strong>
                  {" "}Provjerio sam brojeve pozicija, šifre artikala, nazive i količine te ispravio sve uočene greške.
                </span>
              </label>
            )}

            <div className="inquiry-preview-footer">
              <button
                type="button"
                className="secondary-button"
                onClick={() => {
                  setImportPreview(null)
                  setPdfImportConfirmed(false)
                }}
                disabled={importingInquiry}
              >
                Odustani
              </button>
              <button
                type="button"
                className="primary-button"
                onClick={() => void applyInquiryImport()}
                disabled={
                  importingInquiry ||
                  importPreview.length === 0 ||
                  importValidation.blockingCount > 0 ||
                  (importSourceName.toLocaleLowerCase().endsWith(".pdf") && (
                    !pdfImportConfirmed ||
                    importPreview.some((row, index) =>
                      !row.name.trim() && !importPositionChecks[index]?.useDatabaseData
                    )
                  ))
                }
                title={importSourceName.toLocaleLowerCase().endsWith(".pdf") && !pdfImportConfirmed
                  ? "Prvo pregledaj PDF podatke i potvrdi njihovu ispravnost."
                  : undefined}
              >
                {importingInquiry
                  ? "⏳ Uvoz u toku..."
                  : importValidation.blockingCount > 0
                    ? "Ispravi obavezne greške"
                    : importSourceName.toLocaleLowerCase().endsWith(".pdf") && !pdfImportConfirmed
                      ? "Potvrdi podatke za nastavak"
                      : `Potvrdi i uvezi ${importPreview.length} pozicija`}
              </button>
            </div>
          </section>
        </div>
      )}

      {/* ====================================== */}
      {/* ZAGLAVLJE PONUDE I BRZI ALATI */}
      {/* ====================================== */}
      <div className="page-header quote-page-header">
        <div className="page-title-block">
          <span className="page-title-eyebrow">CNC QUOTING SYSTEM</span>
          <h1>{isEditing ? "Uredi ponudu" : "Nova ponuda"}</h1>
          <p>Kalkulacija CNC pozicija i priprema ponude.</p>
        </div>

        {isNewQuoteTimerSessionRef.current && (
          <div className={`quote-preparation-timer ${quotePreparationTimerFinished ? "is-finished" : "is-running"}`}>
            <span className="quote-preparation-timer-icon" aria-hidden="true">◷</span>
            <div className="quote-preparation-timer-copy">
              <span>Vrijeme izrade ponude</span>
              <strong>{formatQuotePreparationClock(quotePreparationElapsedMs)}</strong>
              <small>{quotePreparationTimerFinished ? "Vrijeme sačuvano" : "Mjerenje je u toku"}</small>
            </div>
          </div>
        )}

        <div className={`quote-draft-status is-${draftSyncStatus}`} role="status" title={draftSyncMessage || "Automatsko čuvanje radne ponude"}>
          <span className="quote-draft-status-icon" aria-hidden="true">
            {draftSyncStatus === "saving" || draftSyncStatus === "loading" ? "⟳" : draftSyncStatus === "error" ? "⚠" : "✓"}
          </span>
          <div>
            <strong>
              {draftSyncStatus === "loading" ? "Provjera radne ponude" :
                draftSyncStatus === "saving" ? "Snimanje..." :
                  draftSyncStatus === "saved" ? "Automatski sačuvano" :
                    draftSyncStatus === "local" ? "Sačuvano lokalno" :
                      draftSyncStatus === "error" ? "Čuvanje samo lokalno" : "AutoSave spreman"}
            </strong>
            <small>{draftSyncMessage || "Promjene se automatski čuvaju."}</small>
          </div>
        </div>

        <div className="page-header-actions">
          <button
            type="button"
            className="secondary-button export-button"
            onClick={handleExportPdf}
            disabled={editingQuoteLoading || exportingFormat !== null}
          >
            {exportingFormat === "pdf" ? "⏳ Priprema PDF-a..." : "📄 Izvezi PDF"}
          </button>

          <button
            type="button"
            className="secondary-button export-button quote-tg-export-button"
            onClick={openTgExportModal}
            disabled={editingQuoteLoading || exportingFormat !== null}
          >
            📄 Izvezi PDF TG
          </button>

          <button
            type="button"
            className="secondary-button export-button"
            onClick={handleExportExcel}
            disabled={editingQuoteLoading || exportingFormat !== null}
          >
            {exportingFormat === "excel" ? "⏳ Priprema Excela..." : "📊 Izvezi Excel"}
          </button>

          <button
            type="button"
            className="primary-button save-quote-button"
            onClick={() => void saveQuote()}
            disabled={editingQuoteLoading || exportingFormat !== null}
          >
            {editingQuoteLoading
              ? "⏳ Učitavanje..."
              : isEditing || savedQuoteId
                ? "💾 Sačuvaj izmjene"
                : "💾 Sačuvaj ponudu"}
          </button>
        </div>
      </div>

      <div className="quote-tools-grid">
        <section className="quote-customer-section" aria-label="Kupac ponude">
          <div className="form-card quote-customer-card">
            <div className="quote-tool-heading">
              <span className="quote-tool-icon" aria-hidden="true">♙</span>
              <div>
                <h3>Kupac</h3>
                <p>Odaberi firmu za koju pripremaš ponudu.</p>
              </div>
            </div>

            <label className="quote-customer-label">
              Odaberi kupca
              <select
                value={selectedCustomerId}
                disabled={customersLoading}
                onChange={(event) => setSelectedCustomerId(event.target.value)}
              >
                <option value="">-- Odaberi kupca --</option>
                {customers.map((customer) => (
                  <option key={customer.id} value={customer.id}>
                    {customer.naziv}
                  </option>
                ))}
              </select>
            </label>
            {customersLoading && <span className="quote-tool-help">Učitavanje kupaca...</span>}
          </div>
        </section>

        <section className="quote-inquiry-number-section" aria-label="Broj upita kupca">
          <div className="form-card quote-inquiry-number-card">
            <div className="quote-tool-heading">
              <span className="quote-tool-icon" aria-hidden="true">#</span>
              <div>
                <h3>Broj upita</h3>
                <p>Opcionalni broj upita kupca.</p>
              </div>
            </div>

            <label className="quote-inquiry-number-label" htmlFor="customer-inquiry-number">
              Broj upita kupca (nije obavezno)
              <input
                id="customer-inquiry-number"
                type="text"
                value={customerInquiryNumber}
                onChange={(event) => setCustomerInquiryNumber(event.target.value)}
                placeholder="npr. 61045570"
                maxLength={100}
                autoComplete="off"
              />
            </label>
            <span className="quote-tool-help">Ako kupac nije naveo broj upita, ostavi prazno.</span>
          </div>
        </section>

        <section className="inquiry-import-toolbar" aria-label="Uvoz upita kupca">
          <div className="inquiry-import-heading">
            <span className="quote-tool-kicker">UVOZ DOKUMENTACIJE</span>
            <strong>Uvoz upita kupca</strong>
            <span>Uvezi Excel ili tekstualni PDF upit. Prije uvoza možeš provjeriti i urediti prepoznate pozicije.</span>
          </div>

          <div className="inquiry-import-actions">
            <input
              ref={excelInquiryInputRef}
              type="file"
              accept=".xlsx,.xls,.xlsm,.csv,.ods"
              className="inquiry-file-input"
              aria-label="Odaberi Excel datoteku upita"
              onChange={(event) => void handleInquiryFileSelect(event)}
            />
            <input
              ref={pdfInquiryInputRef}
              type="file"
              accept=".pdf,application/pdf"
              className="inquiry-file-input"
              aria-label="Odaberi PDF datoteku upita"
              onChange={(event) => void handleInquiryFileSelect(event)}
            />
            <button
              type="button"
              className="secondary-button inquiry-upload-button"
              disabled={importingInquiry || exportingFormat !== null || editingQuoteLoading}
              onClick={() => excelInquiryInputRef.current?.click()}
            >
              {importingInquiry ? "⏳ Učitavanje..." : "📊 Uvezi Excel upit"}
            </button>
            <button
              type="button"
              className="secondary-button inquiry-upload-button"
              disabled={importingInquiry || exportingFormat !== null || editingQuoteLoading}
              onClick={() => pdfInquiryInputRef.current?.click()}
            >
              {importingInquiry ? "⏳ Učitavanje..." : "📄 Uvezi PDF upit"}
            </button>
          </div>

          {importError && <p className="inquiry-import-error" role="alert">{importError}</p>}
          {!importPreview && importSourceName && customerRecognitionMessage && (
            <div className="inquiry-import-info customer-recognition-info" role="status">
              <div>{customerRecognitionMessage}</div>
              {customerRecognitionSuggestions.length > 0 && (
                <div className="customer-recognition-suggestions">
                  {customerRecognitionSuggestions.map((customer) => (
                    <button
                      key={customer.id || customer.naziv}
                      type="button"
                      className="secondary-button"
                      onClick={() => chooseRecognizedCustomer(customer)}
                      disabled={!customer.id}
                    >
                      Odaberi: {customer.naziv}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}
        </section>
      </div>

      <section className="staffel-settings-panel" aria-label="Količinske cijene">
        <div className="staffel-settings-heading">
          <div className="staffel-settings-icon" aria-hidden="true">≋</div>
          <div>
            <h2>Količinske cijene (Staffelpreise)</h2>
            <p>Uključi kada kupac traži prodajnu cijenu za više različitih količina. Cijene se izračunavaju zasebno za svaku količinu i svaku poziciju.</p>
          </div>
          <label className="staffel-enable-toggle">
            <input
              type="checkbox"
              checked={staffelPricesEnabled}
              onChange={(event) => setStaffelPricesEnabled(event.target.checked)}
            />
            <span>Uključi Staffelpreise</span>
          </label>
        </div>

        {staffelPricesEnabled && (
          <div className="staffel-settings-body">
            <div className="staffel-mode-options" role="radiogroup" aria-label="Način unosa dodatnih količina">
              <label>
                <input
                  type="radio"
                  name="staffel-mode"
                  checked={staffelMode === "shared"}
                  onChange={() => changeStaffelMode("shared")}
                />
                Iste količine za sve pozicije
              </label>
              <label>
                <input
                  type="radio"
                  name="staffel-mode"
                  checked={staffelMode === "per-position"}
                  onChange={() => changeStaffelMode("per-position")}
                />
                Posebne količine za svaku poziciju
              </label>
            </div>

            {staffelMode === "shared" ? (
              <>
                <label className="staffel-quantity-label">
                  Tražene količine za sve pozicije (komadi; zarez ili tačka-zarez)
                  <input
                    type="text"
                    value={staffelQuantityText}
                    onChange={(event) => setStaffelQuantityText(event.target.value)}
                    placeholder="1, 2, 3, 5, 10, 15, 20"
                    aria-invalid={staffelQuantityInputInvalid}
                  />
                </label>
                <div className="staffel-quantity-summary">
                  {staffelQuantities.length > 0
                    ? staffelQuantities.map((quantity) => <span key={quantity}>{quantity} kom.</span>)
                    : <span className="staffel-error">Unesi barem jednu pozitivnu količinu.</span>}
                </div>
                {staffelQuantityInputInvalid && (
                  <p className="staffel-error" role="alert">Koristi pozitivne cijele brojeve, razdvojene zarezom ili tačka-zarezom. Primjer: 1; 2; 4.</p>
                )}
                {staffelQuantities.length > 30 && (
                  <p className="staffel-error" role="alert">Maksimalno je dozvoljeno 30 različitih količina po ponudi.</p>
                )}
              </>
            ) : (
              <p className="staffel-help">Unesi količine odvojene tačka-zarezom ili zarezom u novoj koloni "Price Scale / Dodatne količine" u tabeli pozicija. Svaka pozicija može imati različite količine. Kod prelaska iz zajedničkog načina, postojeća lista je kopirana u prazne pozicije.</p>
            )}
            <p className="staffel-help">Programiranje, priprema i trošak kontrole za seriju raspoređuju se prema svakoj količini. PDF i Excel prikazuju alternativne količine, a ne zbir narudžbi. Osnovna "Required Qty" ostaje odvojena od cjenovnih nivoa.</p>
          </div>
        )}
      </section>

      {/* ====================================== */}
      {/* GORNJA TABELA */}
      {/* ====================================== */}

      <section className="positions-section">

        <div
          className="paste-area"
          title="Klikni bilo gdje u ovom polju i pritisni Ctrl+V da zalijepiš pozicije iz Excela."
          style={{
            position: "relative",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            gap: "16px",
            flexWrap: "wrap",
            cursor: "text",
          }}
        >

          <strong>
            Paste iz Excela
          </strong>

          {/*
            Ne prikazujemo zasebni textarea kontrolni okvir.
            On prekriva cijelo polje, pa se korisnik može kliknuti bilo gdje
            u isprekidani okvir i zalijepiti kopirane Excel redove pomoću Ctrl+V.
            onPaste i handlePaste ostaju aktivni.
          */}
          <textarea
            aria-label="Paste pozicija iz Excela"
            title="Zalijepi pozicije iz Excela ovdje (Ctrl+V)"
            placeholder=""
            onPaste={handlePaste}
            rows={1}
            style={{
              position: "absolute",
              inset: 0,
              width: "100%",
              height: "100%",
              boxSizing: "border-box",
              margin: 0,
              padding: 0,
              border: 0,
              opacity: 0,
              resize: "none",
              overflow: "hidden",
              background: "transparent",
              cursor: "text",
              zIndex: 1,
            }}
          />

          <span style={{ marginLeft: "auto", textAlign: "right" }}>
            Podržani formati uključuju Br., Šifra, Naziv, Količina ili kupčev SAP No. / Text / Required Qty / ... / Price Scale.
            Klikni bilo gdje u ovom polju i pritisni Ctrl+V.
          </span>

        </div>

        <div className="section-header">

          <h2>
            Pozicije
          </h2>

          <div className="section-actions">

            <button
              className="secondary-button"
              onClick={
                addPosition
              }
            >
              + Dodaj poziciju
            </button>

            <button
              className="danger-button"
              onClick={
                removePosition
              }
            >
              Obriši
            </button>

          </div>

        </div>

        <div className="table-wrapper">

          <table className="positions-table">

            <thead>

              <tr>
                <th>Br.</th>
                <th>Šifra</th>
                <th>Naziv</th>
                <th>Količina</th>
                {staffelPricesEnabled && staffelMode === "per-position" && <th>Price Scale / Dodatne količine</th>}
                <th>Status u bazi</th>
                <th>Preporučena akcija</th>
              </tr>

            </thead>

            <tbody>

              {positions.map(
                (
                  item,
                  index,
                ) => (

                  <tr
                    key={index}
                    className={
                      index ===
                      selectedIndex
                        ? "selected-row"
                        : ""
                    }
                    onClick={() =>
                      setSelectedIndex(
                        index,
                      )
                    }
                  >

                    <td>
                      {
                        item.positionNumber
                      }
                    </td>

                    <td>

                      <input
                        value={
                          item.code
                        }
                        onChange={(
                          event,
                        ) => {
                          clearPositionLookupStatuses(
                            item.code,
                            event.target.value,
                          )
                          updatePositionAtIndex(
                            index,
                            {
                              code: event.target.value,
                            },
                          )
                        }}
                        onBlur={async (
                          event,
                        ) => {
                          await loadPositionFromDatabase(
                            event.target
                              .value,
                            index,
                          )
                        }}
                        onClick={(
                          event,
                        ) =>
                          event.stopPropagation()
                        }
                        placeholder="Šifra"
                      />

                    </td>

                    <td>

                      <input
                        value={
                          item.name
                        }
                        onChange={(event) => {
                          const nextName = event.target.value
                          updatePositionAtIndex(index, { name: nextName })
                          const normalizedCode = normalizePositionLookupCode(item.code)
                          setPositionImportReviewByCode((current) => {
                            const review = current[normalizedCode]
                            if (!review) return current
                            const mismatch = Boolean(
                              review.databaseName && nextName.trim() &&
                              normalizeComparablePositionName(review.databaseName) !== normalizeComparablePositionName(nextName),
                            )
                            return {
                              ...current,
                              [normalizedCode]: {
                                ...review,
                                importedName: nextName,
                                nameMismatch: mismatch,
                              },
                            }
                          })
                        }}
                        onClick={(
                          event,
                        ) =>
                          event.stopPropagation()
                        }
                        placeholder="Naziv pozicije"
                      />

                    </td>

                    <td>

                      <LocalizedNumberInput
                        min="1"
                        value={item.quantity}
                        onValueChange={(value) => updatePositionAtIndex(index, { quantity: value || 1 })}
                        onClick={(event) => event.stopPropagation()}
                      />

                    </td>

                    {staffelPricesEnabled && staffelMode === "per-position" && (
                      <td className="position-staffel-cell">
                        <input
                          type="text"
                          value={item.staffelQuantityText || ""}
                          placeholder="npr. 1; 2; 4"
                          aria-label={`Dodatne količine za poziciju ${item.positionNumber}`}
                          aria-invalid={Boolean(String(item.staffelQuantityText || "").trim() && hasInvalidStaffelQuantity(item.staffelQuantityText))}
                          onChange={(event) => updatePositionAtIndex(index, { staffelQuantityText: event.target.value })}
                          onClick={(event) => event.stopPropagation()}
                        />
                        {String(item.staffelQuantityText || "").trim() && hasInvalidStaffelQuantity(item.staffelQuantityText) && (
                          <small className="staffel-error">Koristi pozitivne cijele brojeve.</small>
                        )}
                      </td>
                    )}

                    {(() => {
                      const normalizedCode = normalizePositionLookupCode(item.code)
                      const lookupStatus = positionLookupStatuses[normalizedCode]
                      const review = positionImportReviewByCode[normalizedCode]
                      const nameMismatch = Boolean(review?.nameMismatch)
                      const shownStatus = nameMismatch
                        ? "mismatch"
                        : lookupStatus === "found"
                          ? "found"
                          : lookupStatus === "found-skipped"
                            ? "skipped"
                            : lookupStatus === "not-found" || review?.status === "new"
                              ? "new"
                              : lookupStatus === "error" || review?.status === "error"
                                ? "error"
                                : lookupStatus === "loading"
                                  ? "loading"
                                  : "unchecked"
                      const statusLabel = shownStatus === "found"
                        ? "✅ Pozicija postoji"
                        : shownStatus === "skipped"
                          ? "⚠️ Postoji, podaci nisu preuzeti"
                          : shownStatus === "mismatch"
                            ? "⚠️ Podaci se razlikuju"
                            : shownStatus === "new"
                              ? "🆕 Nova pozicija"
                              : shownStatus === "error"
                                ? "❌ Greška provjere"
                                : shownStatus === "loading"
                                  ? "⏳ Provjera u toku"
                                  : "— Nije provjereno"
                      const recommendation = shownStatus === "found"
                        ? "Tehnički podaci su učitani"
                        : shownStatus === "skipped"
                          ? "Provjeri/unesi tehničke podatke"
                          : shownStatus === "mismatch"
                            ? `Provjeri naziv u bazi${review?.databaseName ? `: ${review.databaseName}` : ""}`
                            : shownStatus === "new"
                              ? "Potrebna kalkulacija"
                              : shownStatus === "error"
                                ? "Ponovi provjeru baze"
                                : shownStatus === "loading"
                                  ? "Provjera baze..."
                                  : item.code.trim()
                                    ? "Provjera nije završena"
                                    : "Unesi šifru artikla"
                      return (
                        <>
                          <td>
                            <span className={`main-position-db-status ${shownStatus}`}>
                              {statusLabel}
                            </span>
                          </td>
                          <td className="main-position-db-action">
                            {recommendation}
                          </td>
                        </>
                      )
                    })()}

                  </tr>

                ),
              )}

            </tbody>

          </table>

        </div>

      </section>

      {/* ====================================== */}
      {/* DETALJI POZICIJE */}
      {/* ====================================== */}

      <section className="position-details">

        {copyPositionSourceIndex !== null && (
          <div className="copy-position-modal-backdrop" role="presentation" onMouseDown={(event) => {
            if (event.target === event.currentTarget) setCopyPositionSourceIndex(null)
          }}>
            <div className="copy-position-modal" role="dialog" aria-modal="true" aria-labelledby="copy-position-title">
              <div className="copy-position-modal-header">
                <div>
                  <span className="copy-position-eyebrow">BRZI PRENOS PODATAKA</span>
                  <h3 id="copy-position-title">Kopiraj podatke sa pozicije</h3>
                  <p>Odaberi poziciju iz ove ponude čije tehničke podatke želiš prenijeti.</p>
                </div>
                <button type="button" className="copy-position-close" aria-label="Zatvori" onClick={() => setCopyPositionSourceIndex(null)}>×</button>
              </div>
              <div className="copy-position-list">
                {positions.map((item, index) => index === selectedIndex ? null : (
                  <button
                    type="button"
                    className="copy-position-option"
                    key={`${item.positionNumber}-${index}`}
                    onClick={() => copyPositionData(index)}
                  >
                    <span className="copy-position-option-number">{item.positionNumber || String(index + 1).padStart(3, "0")}</span>
                    <span className="copy-position-option-main">
                      <strong>{item.code || "Bez šifre"}{item.name ? ` — ${item.name}` : ""}</strong>
                      <small>{item.material || "Materijal nije odabran"} · {item.machine || "Mašina nije odabrana"}</small>
                    </span>
                    <span className="copy-position-option-action">Kopiraj →</span>
                  </button>
                ))}
                {positions.length <= 1 && <p className="copy-position-empty">U ponudi nema drugih pozicija iz kojih se mogu kopirati podaci.</p>}
              </div>
              <div className="copy-position-modal-footer">
                <span>Šifra, naziv, količina, marža, transport i prodajna cijena ostaju nepromijenjeni.</span>
                <button type="button" className="secondary-button" onClick={() => setCopyPositionSourceIndex(null)}>Odustani</button>
              </div>
            </div>
          </div>
        )}

        {/* NAVIGACIJA */}

        <div className="position-navigation">

          <button
            className="navigation-button"
            onClick={
              goPrevious
            }
          >
            ←
          </button>

          <div>

            <span className="position-counter">

              Pozicija{" "}
              {selectedIndex + 1}{" "}
              /{" "}
              {positions.length}

            </span>

            <h2>
              {
                position.code ||
                "Nova pozicija"
              }
            </h2>

          </div>

          <button
            className="navigation-button"
            onClick={
              goNext
            }
          >
            →
          </button>

        </div>

        {/* AKCIJE */}


        <div className="position-copy-actions">
          <button
            type="button"
            className="secondary-button copy-position-trigger"
            onClick={() => setCopyPositionSourceIndex(-1)}
            disabled={positions.length <= 1}
            title={positions.length <= 1 ? "Dodaj još jednu poziciju da bi mogao kopirati podatke." : "Kopiraj tehničke i kalkulacijske podatke s druge pozicije"}
          >
            ⧉ Kopiraj podatke s druge pozicije
          </button>
        </div>

        {/* ====================================== */}
        {/* FORMULARI */}
        {/* ====================================== */}

        <div
          className="form-grid"
          onKeyDown={(event) => {
            const target = event.target
            if (!(target instanceof HTMLElement)) return
            if (event.defaultPrevented || event.nativeEvent.isComposing) return

            const isInput = target instanceof HTMLInputElement
            const isSelect = target instanceof HTMLSelectElement
            const isTextArea = target instanceof HTMLTextAreaElement
            if (!isInput && !isSelect && !isTextArea) return
            if (isTextArea) return

            let direction: -1 | 1 | null = null
            if (event.key === "Enter" && !event.ctrlKey && !event.altKey && !event.metaKey) {
              direction = event.shiftKey ? -1 : 1
            } else if (isInput && !event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey) {
              if (event.key === "ArrowDown") direction = 1
              if (event.key === "ArrowUp") direction = -1
            }
            if (direction === null) return

            const fields = Array.from(event.currentTarget.querySelectorAll<HTMLElement>(
              'input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), select, textarea',
            )).filter((field) =>
              !field.hasAttribute("disabled") &&
              field.getClientRects().length > 0 &&
              field.getAttribute("aria-hidden") !== "true"
            )
            const currentIndex = fields.indexOf(target)
            if (currentIndex < 0) return

            event.preventDefault()
            const nextIndex = currentIndex + direction
            if (nextIndex >= 0 && nextIndex < fields.length) {
              fields[nextIndex].focus()
              return
            }

            if (positions.length > 1) {
              focusAfterPositionChangeRef.current = direction > 0 ? "first" : "last"
              if (direction > 0) goNext()
              else goPrevious()
            } else {
              const wrapIndex = direction > 0 ? 0 : fields.length - 1
              fields[wrapIndex]?.focus()
            }
          }}
        >

          {/* OSNOVNI PODACI */}

          <div className="form-card">

            <h3>
              Osnovni podaci
            </h3>

            <label>

              Šifra

              <input
                value={
                  position.code
                }
                onChange={(
                  event,
                ) => {
                  clearPositionLookupStatuses(
                    position.code,
                    event.target.value,
                  )
                  updatePosition({
                    code: event.target.value,
                  })
                }}
                onBlur={async (
                  event,
                ) => {
                  await loadPositionFromDatabase(
                    event.target
                      .value,
                  )
                }}
              />

            </label>

            {position.code.trim() && currentPositionLookupStatus && (
              <p
                className={`position-lookup-feedback ${currentPositionLookupStatus}`}
                role="status"
                aria-live="polite"
              >
                {currentPositionLookupStatus === "loading" &&
                  "Provjeravam da li šifra postoji u bazi..."}
                {currentPositionLookupStatus === "found" &&
                  "Pronađena ranija kalkulacija. Tehnički podaci su učitani; naziv i količina iz upita ostaju sačuvani."}
                {currentPositionLookupStatus === "found-skipped" &&
                  "Pozicija postoji u bazi, ali zbog razlike u nazivu tehnički podaci iz baze nisu automatski preuzeti. Postojeći zapis u bazi neće biti prepisan ovim nacrtom."}
                {currentPositionLookupStatus === "not-found" &&
                  "Ova šifra još nije u bazi. Nakon snimanja ponude, podaci će biti dostupni za sljedeće kalkulacije."}
                {currentPositionLookupStatus === "error" &&
                  "Pretraga baze nije uspjela. Pokušaj ponovo ili provjeri pristup."}
              </p>
            )}

            {position.code.trim() && (
              <div className="previous-quote-price" role="status" aria-live="polite">
                <div className="previous-quote-price-heading">
                  Posljednja cijena iz ranije ponude
                </div>

                {quoteHistoryLoading && (
                  <p className="previous-quote-price-message">
                    Provjeravam ranije ponude...
                  </p>
                )}

                {!quoteHistoryLoading && quoteHistoryError && (
                  <p className="previous-quote-price-message error">
                    Historiju cijena trenutno nije moguće učitati.
                  </p>
                )}

                {!quoteHistoryLoading && !quoteHistoryError && previousQuotePrice && (
                  <>
                    <div className="previous-quote-price-value">
                      {formatPreviousPriceMoney(previousQuotePrice.pricePerPiece)} / kom
                    </div>
                    <div className="previous-quote-price-details">
                      Ponuda {previousQuotePrice.quoteNumber} · {formatPreviousPriceDate(previousQuotePrice.dateValue)}
                      <br />
                      Kupac: {previousQuotePrice.customerName} · Količina: {previousQuotePrice.quantity}
                    </div>
                    <div className="previous-quote-price-note">
                      Informativni podatak. Cijena se ne preuzima automatski u novu kalkulaciju.
                    </div>
                    {previousPriceComparison && (
                      <div
                        className={`previous-price-comparison ${previousPriceComparison.direction}`}
                        aria-live="polite"
                      >
                        <div className="previous-price-comparison-heading">
                          Poređenje s trenutnom kalkulacijom
                        </div>
                        <div className="previous-price-comparison-current">
                          Trenutna cijena: <strong>{formatPreviousPriceMoney(previousPriceComparison.currentPrice)} / kom</strong>
                        </div>
                        <div className="previous-price-comparison-delta">
                          {previousPriceComparison.direction === "higher" && "Nova cijena je viša za "}
                          {previousPriceComparison.direction === "lower" && "Nova cijena je niža za "}
                          {previousPriceComparison.direction === "same" && "Nova cijena je na nivou posljednje ponude "}
                          <strong>
                            {previousPriceComparison.direction === "higher" ? "+" : previousPriceComparison.direction === "lower" ? "−" : ""}
                            {formatPreviousPriceMoney(Math.abs(previousPriceComparison.difference))} / kom
                            ({previousPriceComparison.direction === "higher" ? "+" : previousPriceComparison.direction === "lower" ? "−" : ""}
                            {Math.abs(previousPriceComparison.percentage).toLocaleString("bs-BA", {
                              minimumFractionDigits: 1,
                              maximumFractionDigits: 1,
                            })}%)
                          </strong>
                          {" "}u odnosu na posljednju ponudu.
                        </div>
                      </div>
                    )}
                    {previousQuotePrices.length > 1 && (
                      <details className="previous-quote-history">
                        <summary>
                          Prikaži historiju cijena ({previousQuotePrices.length} ponuda)
                        </summary>
                        <div className="previous-quote-history-table-wrapper">
                          <table className="previous-quote-history-table">
                            <thead>
                              <tr>
                                <th>Datum</th>
                                <th>Broj ponude</th>
                                <th>Kupac</th>
                                <th>Količina</th>
                                <th>Cijena / kom</th>
                              </tr>
                            </thead>
                            <tbody>
                              {previousQuotePrices.map((item, index) => (
                                <tr
                                  key={item.quoteId || `${item.quoteNumber}-${item.dateMillis}-${index}`}
                                  className={index === 0 ? "latest-history-price" : undefined}
                                >
                                  <td>{formatPreviousPriceDate(item.dateValue)}</td>
                                  <td>{item.quoteNumber}</td>
                                  <td>{item.customerName}</td>
                                  <td>{item.quantity}</td>
                                  <td>{formatPreviousPriceMoney(item.pricePerPiece)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </details>
                    )}
                  </>
                )}

                {!quoteHistoryLoading && !quoteHistoryError && !previousQuotePrice && (
                  <p className="previous-quote-price-message">
                    Nema ranije sačuvane cijene za ovu šifru.
                  </p>
                )}
              </div>
            )}

            <label>

              Naziv

              <input
                value={
                  position.name
                }
                onChange={(
                  event,
                ) =>
                  updatePosition({
                    name:
                      event.target
                        .value,
                  })
                }
              />

            </label>

            <label>

              Količina

              <LocalizedNumberInput
                min="1"
                value={position.quantity}
                onValueChange={(value) => updatePosition({ quantity: value || 1 })}
              />

            </label>

            <div className="shape-field">
              <div className="shape-field-heading">
                <span className="shape-field-label">Oblik</span>
                <span className="shape-field-hint">Odaberi oblik sirovine</span>
              </div>

              <div className="shape-selector" role="group" aria-label="Odabir oblika sirovine">
                <button
                  type="button"
                  className={`shape-option ${position.shape === "rectangular" ? "selected" : ""}`}
                  aria-label="Pravougaoni komad"
                  aria-pressed={position.shape === "rectangular"}
                  title="Pravougaoni komad"
                  onClick={() => handleShapeChange("rectangular")}
                >
                  <svg viewBox="0 0 32 32" aria-hidden="true">
                    <rect x="4" y="4" width="24" height="24" fill="currentColor" />
                  </svg>
                </button>

                <button
                  type="button"
                  className={`shape-option ${position.shape === "cylinder" ? "selected" : ""}`}
                  aria-label="Okrugli puni materijal"
                  aria-pressed={position.shape === "cylinder"}
                  title="Okrugli puni materijal (valjak)"
                  onClick={() => handleShapeChange("cylinder")}
                >
                  <svg viewBox="0 0 32 32" aria-hidden="true">
                    <circle cx="16" cy="16" r="12" fill="currentColor" />
                  </svg>
                </button>

                <button
                  type="button"
                  className={`shape-option ${position.shape === "l-profile" ? "selected" : ""}`}
                  aria-label="L-profil"
                  aria-pressed={position.shape === "l-profile"}
                  title="L-profil"
                  onClick={() => handleShapeChange("l-profile")}
                >
                  <svg viewBox="0 0 32 32" aria-hidden="true">
                    <path d="M5 4h8v16h15v8H5z" fill="currentColor" />
                  </svg>
                </button>

                <button
                  type="button"
                  className={`shape-option ${position.shape === "square-tube" ? "selected" : ""}`}
                  aria-label="Kvadratna cijev, šuplji profil"
                  aria-pressed={position.shape === "square-tube"}
                  title="Kvadratna cijev / šuplji profil"
                  onClick={() => handleShapeChange("square-tube")}
                >
                  <svg viewBox="0 0 32 32" aria-hidden="true">
                    <rect x="5" y="5" width="22" height="22" fill="none" stroke="currentColor" strokeWidth="6" />
                  </svg>
                </button>

                <button
                  type="button"
                  className={`shape-option ${position.shape === "round-tube" ? "selected" : ""}`}
                  aria-label="Okrugla cijev"
                  aria-pressed={position.shape === "round-tube"}
                  title="Okrugla cijev"
                  onClick={() => handleShapeChange("round-tube")}
                >
                  <svg viewBox="0 0 32 32" aria-hidden="true">
                    <circle cx="16" cy="16" r="10.5" fill="none" stroke="currentColor" strokeWidth="7" />
                  </svg>
                </button>
              </div>

              <details
                className="additional-shapes"
                open={[
                  "hexagon",
                  "octagon",
                  "i-profile",
                  "h-profile",
                  "u-profile",
                  "t-profile",
                  "rectangular-tube",
                  "custom-profile",
                ].includes(position.shape)}
              >
                <summary>Ostali oblici i profili</summary>
                <div className="additional-shape-options" role="group" aria-label="Ostali oblici i profili">
                  {([
                    ["hexagon", "Šestkant"],
                    ["octagon", "Osmokant"],
                    ["i-profile", "I-profil"],
                    ["h-profile", "H-profil"],
                    ["u-profile", "U/C-profil"],
                    ["t-profile", "T-profil"],
                    ["rectangular-tube", "Pravougaona cijev"],
                    ["custom-profile", "Drugi profil"],
                  ] as [Shape, string][]).map(([shape, label]) => (
                    <button
                      key={shape}
                      type="button"
                      className={`additional-shape-option ${position.shape === shape ? "selected" : ""}`}
                      aria-pressed={position.shape === shape}
                      onClick={() => handleShapeChange(shape)}
                    >
                      {label}
                    </button>
                  ))}
                </div>
              </details>
            </div>

          </div>

          {/* DIMENZIJE I PRESJEK SIROVINE */}

          <div className="form-card">
            <h3>Dimenzije</h3>

            {position.shape === "rectangular" && <>
              {renderDimensionInput("Dužina [mm]", "length")}
              {renderDimensionInput("Širina [mm]", "width")}
              {renderDimensionInput("Visina [mm]", "height")}
            </>}

            {(position.shape === "cylinder" || position.shape === "round-tube") && <>
              {renderDimensionInput(position.shape === "round-tube" ? "Vanjski prečnik [mm]" : "Prečnik [mm]", "diameter")}
              {renderDimensionInput("Dužina [mm]", "length")}
              {position.shape === "round-tube" && renderDimensionInput("Debljina stijenke [mm]", "thickness")}
            </>}

            {(position.shape === "hexagon" || position.shape === "octagon") && <>
              {renderDimensionInput("Širina preko ravnih strana [mm]", "width")}
              {renderDimensionInput("Dužina [mm]", "length")}
            </>}

            {position.shape === "l-profile" && <>
              {renderDimensionInput("Dužina profila [mm]", "length")}
              {renderDimensionInput("Širina vodoravnog kraka [mm]", "width")}
              {renderDimensionInput("Visina uspravnog kraka [mm]", "height")}
              {renderDimensionInput("Debljina profila [mm]", "thickness")}
            </>}

            {(position.shape === "i-profile" || position.shape === "h-profile" || position.shape === "u-profile" || position.shape === "t-profile") && <>
              {renderDimensionInput("Dužina profila [mm]", "length")}
              {renderDimensionInput("Ukupna širina / širina prirubnice [mm]", "width")}
              {renderDimensionInput("Ukupna visina profila [mm]", "height")}
              {renderDimensionInput("Debljina prirubnice [mm]", "flangeThickness")}
              {renderDimensionInput("Debljina rebra [mm]", "webThickness")}
            </>}

            {position.shape === "square-tube" && <>
              {renderDimensionInput("Dužina [mm]", "length")}
              {renderDimensionInput("Vanjska stranica [mm]", "width")}
              {renderDimensionInput("Debljina stijenke [mm]", "thickness")}
            </>}

            {position.shape === "rectangular-tube" && <>
              {renderDimensionInput("Dužina [mm]", "length")}
              {renderDimensionInput("Vanjska širina [mm]", "width")}
              {renderDimensionInput("Vanjska visina [mm]", "height")}
              {renderDimensionInput("Debljina stijenke [mm]", "thickness")}
            </>}

            {position.shape === "custom-profile" && <>
              {renderDimensionInput("Dužina profila [mm]", "length")}
              {renderDimensionInput("Površina poprečnog presjeka [mm²]", "customCrossSectionArea")}
              <p className="dimension-note">
                Koristi ovu opciju za presjeke koji nisu navedeni u meniju. Unesi površinu presjeka iz crteža ili tehničkog kataloga; težina se računa kao površina × dužina × gustina materijala.
              </p>
            </>}

            <label className="weight-input-field raw-weight-field">
              Težina sirovog komada [kg]
              <LocalizedNumberInput
                min="0"
                step="0.001"
                value={position.rawWeightInput}
                placeholder={calculations.weightKg > 0 && position.rawWeightKg <= 0 ? formatLocalizedNumber(Number(calculations.weightKg.toFixed(3))) : "0,000"}
                onValueChange={(value) => updatePosition({
                  rawWeightInput: formatLocalizedNumber(value),
                  rawWeightKg: Math.max(0, value),
                })}
              />
              <small>
                Unesi težinu ako ne želiš unositi dimenzije. Ako je unesena, ova težina ima prednost u obračunu sirovog materijala; u suprotnom se računa iz dimenzija.
              </small>
            </label>

            {calculations.geometryWarning && (
              <p className="dimension-warning" role="status">
                {calculations.geometryWarning} Trošak materijala i težina bit će 0 dok ne uneseš ispravne dimenzije ili težinu sirovog komada.
              </p>
            )}

            {(position.shape === "l-profile" || position.shape === "i-profile" || position.shape === "h-profile" || position.shape === "u-profile" || position.shape === "t-profile" || position.shape === "square-tube" || position.shape === "rectangular-tube" || position.shape === "round-tube") && (
              <p className="dimension-note">
                Obračun koristi idealizirani presjek i ne uključuje radijuse zaobljenja, kosine ni odstupanja standardnih profila. Za preciznu nabavnu kalkulaciju provjeri masu po metru iz kataloga dobavljača.
              </p>
            )}
          </div>

          {/* MATERIJAL */}

          <div className="form-card">

            <h3>
              Materijal
            </h3>

            <label>

              Materijal

              <select
                value={
                  position.material
                }
                onChange={(
                  event,
                ) =>
                  updatePosition({
                    material:
                      event.target
                        .value,
                  })
                }
              >

                {materialOptions.map((item) => (
                  <option
                    key={item.code}
                    value={item.code}
                    disabled={!item.active}
                  >
                    {item.label}
                  </option>
                ))}

              </select>

            </label>

            {materialsLoading ? (
              <p style={{ color: "#64748b", fontSize: 12, lineHeight: 1.4, margin: "8px 0" }}>Učitavanje baze materijala...</p>
            ) : materialsLoadError ? (
              <p style={{ color: "#b45309", fontSize: 12, lineHeight: 1.4, margin: "8px 0" }}>Baza materijala nije dostupna. Privremeno se koriste postojeće početne vrijednosti; provjeri Firebase i prava pristupa.</p>
            ) : databaseMaterials.length === 0 ? (
              <p style={{ color: "#b45309", fontSize: 12, lineHeight: 1.4, margin: "8px 0" }}>Baza materijala je prazna. Privremeno se koriste početne vrijednosti iz starog kalkulatora. U stranici "Materijali" dodaj početne materijale da bi cijene dolazile iz baze.</p>
            ) : null}

            <div className="calculation-row">

              <span>
                Gustina
              </span>

              <strong>
                {
                  selectedMaterialDetails.density
                }{" "}
                kg/m³
              </strong>

            </div>

            <div className="calculation-row">

              <span>
                Cijena materijala
              </span>

              <strong>
                {
                  selectedMaterialDetails.pricePerKg
                }{" "}
                €/kg
              </strong>

            </div>

            <div className="calculation-row">
              <span>Površina presjeka</span>
              <strong>{calculations.crossSectionAreaMm2 > 0
                ? `${calculations.crossSectionAreaMm2.toLocaleString("bs-BA", { maximumFractionDigits: 2 })} mm²`
                : "—"}</strong>
            </div>

            <div className="calculation-row highlight">

              <span>
                Ulazna težina sirovine
              </span>

              <strong>
                {
                  calculations.weightKg.toFixed(
                    3,
                  )
                }{" "}
                kg
              </strong>

            </div>

            <div className="calculation-row highlight">

              <span>
                Materijal / komad
              </span>

              <strong>
                {
                  calculations.materialCost.toFixed(
                    2,
                  )
                }{" "}
                €
              </strong>

            </div>

            <label className="weight-input-field output-weight-field">
              Stvarna težina nakon obrade [kg]
              <LocalizedNumberInput
                min="0"
                step="0.001"
                value={position.outputWeightInput}
                placeholder={calculations.weightKg > 0 ? formatLocalizedNumber(Number(calculations.weightKg.toFixed(3))) : "0,000"}
                onValueChange={(value) => updatePosition({
                  outputWeightInput: formatLocalizedNumber(value),
                  outputWeightKg: Math.max(0, value),
                })}
              />
              <small>
                {position.outputWeightKg > 0
                  ? `Troškovi transporta, površinske zaštite i termičke obrade računaju se na ${calculations.outputWeightKg.toFixed(3)} kg.`
                  : `Nije unesena izlazna težina. Privremeno se koristi ulazna težina sirovine (${calculations.weightKg.toFixed(3)} kg).`}
              </small>
            </label>

          </div>

          {/* MAŠINA */}

          <div className="form-card">

            <h3>
              Mašina i vrijeme
            </h3>

            <label>

              Mašina

              <select
                value={
                  position.machine
                }
                onChange={(
                  event,
                ) =>
                  updatePosition({
                    machine: event.target.value,
                    machineProgrammingRate: undefined,
                    machineSetupRate: undefined,
                    machineMachiningRate: undefined,
                  })
                }
              >

                {machineOptions.map((item) => (
                  <option
                    key={item.code}
                    value={item.code}
                    disabled={!item.active}
                  >
                    {item.label}
                  </option>
                ))}

              </select>

            </label>

            {machinesLoading ? (
              <p style={{ color: "#64748b", fontSize: 12, lineHeight: 1.4, margin: "8px 0" }}>Učitavanje baze mašina i satnica...</p>
            ) : machinesLoadError ? (
              <p style={{ color: "#b45309", fontSize: 12, lineHeight: 1.4, margin: "8px 0" }}>Baza mašina nije dostupna. Privremeno se koriste početne satnice; provjeri Firebase i Firestore pravila.</p>
            ) : databaseMachines.length === 0 ? (
              <p style={{ color: "#b45309", fontSize: 12, lineHeight: 1.4, margin: "8px 0" }}>Baza mašina je prazna. Privremeno se koriste stare satnice. U stranici "Mašine i satnice" dodaj početne mašine da bi se cijene učitavale iz baze.</p>
            ) : null}

            <div className="calculation-row">
              <span>Satnica programiranja</span>
              <strong>{calculations.machineProgrammingRate.toFixed(2)} €/h</strong>
            </div>

            <div className="calculation-row">
              <span>Satnica pripreme</span>
              <strong>{calculations.machineSetupRate.toFixed(2)} €/h</strong>
            </div>

            <div className="calculation-row">
              <span>Satnica obrade</span>
              <strong>{calculations.machineMachiningRate.toFixed(2)} €/h</strong>
            </div>

            <label>

              Programiranje [min]

              <LocalizedNumberInput
                min="0"
                value={position.programmingMinutes}
                allowFormula
                placeholder="npr. 15+20"
                onValueChange={(value) => updatePosition({ programmingMinutes: value })}
              />

            </label>

            <label>

              Priprema [min]

              <LocalizedNumberInput
                min="0"
                value={position.setupMinutes}
                allowFormula
                placeholder="npr. 15+20"
                onValueChange={(value) => updatePosition({ setupMinutes: value })}
              />

            </label>

            <label>

              Izrada [min]

              <LocalizedNumberInput
                min="0"
                value={position.machiningMinutes}
                allowFormula
                placeholder="npr. 15+20"
                onValueChange={(value) => updatePosition({ machiningMinutes: value })}
              />

            </label>

            <small className="number-input-hint">
              Vrijeme možeš unijeti i kao formulu, npr. 15+20 ili (60/4)+5. Pritisni Enter za izračun. Decimalni zarez je podržan.
            </small>

          </div>

          {/* ====================================== */}
          {/* POVRŠINSKA I TERMIČKA OBRADA */}
          {/* ====================================== */}

          <div className="form-card">

            <h3>
              Površinska, termička i ostala obrada
            </h3>

            <label>

              Površinska zaštita

              <select
                value={
                  position.surfaceProtectionId
                }
                disabled={
                  treatmentsLoading
                }
                onChange={(
                  event,
                ) =>
                  handleSurfaceProtectionChange(
                    event.target.value,
                  )
                }
              >

                <option value="">
                  Bez površinske zaštite
                </option>

                {surfaceProtections.map(
                  (item) => (

                    <option
                      key={item.id}
                      value={item.id}
                    >
                      {item.naziv}
                    </option>

                  ),
                )}

              </select>

            </label>

            {position.surfaceProtectionName && (
              <>
                <div className="calculation-row">

                  <span>
                    Odabrana zaštita
                  </span>

                  <strong>
                    {
                      position.surfaceProtectionName
                    }
                  </strong>

                </div>

                <div className="calculation-row highlight">

                  <span>
                    Cijena zaštite / komad
                  </span>

                  <strong>
                    {calculations.surfaceProtectionCost.toFixed(2)}{" "}
                    €
                  </strong>

                </div>
              </>
            )}

            <label>

              Termička obrada

              <select
                value={
                  position.heatTreatmentId
                }
                disabled={
                  treatmentsLoading
                }
                onChange={(
                  event,
                ) =>
                  handleHeatTreatmentChange(
                    event.target.value,
                  )
                }
              >

                <option value="">
                  Bez termičke obrade
                </option>

                {heatTreatments.map(
                  (item) => (

                    <option
                      key={item.id}
                      value={item.id}
                    >
                      {item.naziv}
                    </option>

                  ),
                )}

              </select>

            </label>

            {position.heatTreatmentName && (
              <>
                <div className="calculation-row">

                  <span>
                    Odabrana obrada
                  </span>

                  <strong>
                    {
                      position.heatTreatmentName
                    }
                  </strong>

                </div>

                <div className="calculation-row highlight">

                  <span>
                    Cijena obrade / komad
                  </span>

                  <strong>
                    {calculations.heatTreatmentCost.toFixed(2)}{" "}
                    €
                  </strong>

                </div>
              </>
            )}

            <label>
              Ostali zahvati
              <select
                value={position.otherOperationId}
                disabled={treatmentsLoading}
                onChange={(event) => handleOtherOperationChange(event.target.value)}
              >
                <option value="">Bez dodatnog zahvata</option>
                {otherOperations.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.naziv}
                  </option>
                ))}
              </select>
            </label>

            {position.otherOperationName && (
              <div className="calculation-row highlight">
                <span>Cijena zahvata / komad</span>
                <strong>{calculations.otherOperationCost.toFixed(2)} €</strong>
              </div>
            )}

            {treatmentsLoading && (
              <p
                style={{
                  color: "#6b7280",
                  fontSize: "13px",
                  marginTop: "10px",
                }}
              >
                Učitavanje zaštita, termičkih obrada i ostalih zahvata...
              </p>
            )}

          </div>

          {/* ====================================== */}
          {/* MARŽA */}
          {/* ====================================== */}

          <div className="form-card">

            <h3>
              Marža i prodajna cijena
            </h3>
            <p style={{ fontSize: "12px", color: "#64748b", marginTop: "-8px" }}>
              Marža se primjenjuje samo na poziciju {position.positionNumber || selectedIndex + 1}.
            </p>

            <label>

              Marža [%]

              <LocalizedNumberInput
                min="0"
                max="200"
                step="0.5"
                value={position.marginPercent ?? marginPercent}
                onValueChange={handleMarginChange}
              />

            </label>

            <div
              style={{
                marginTop: "8px",
                marginBottom: "16px",
              }}
            >

              <span
                style={{
                  display: "block",
                  color: "#6b7280",
                  fontSize: "13px",
                  marginBottom: "8px",
                }}
              >
                Brzi izbor:
              </span>

              <div
                style={{
                  display: "flex",
                  flexWrap: "wrap",
                  gap: "8px",
                }}
              >

                {[10, 15, 20, 25, 30, 50, 100, 200].map(
                  (value) => (

                    <button
                      key={value}
                      type="button"
                      className={
                        (position.marginPercent ?? marginPercent) ===
                        value
                          ? "primary-button"
                          : "secondary-button"
                      }
                      onClick={() =>
                        updatePosition({ marginPercent: value })
                      }
                    >
                      {value}%
                    </button>

                  ),
                )}

              </div>

            </div>

            <div className="calculation-row">

              <span>
                Trošak kontrole za seriju
              </span>

              <LocalizedNumberInput
                min="0"
                step="0.01"
                value={position.inspectionCostSeries}
                onValueChange={(value) => updatePosition({ inspectionCostSeries: value })}
                style={{
                  width: "120px",
                  textAlign: "right",
                }}
              />

              <span>€</span>

            </div>

            <div className="calculation-row">

              <span>
                Trošak / komad
              </span>

              <strong>
                {
                  calculations.totalCostPerPiece.toFixed(
                    2,
                  )
                }{" "}
                €
              </strong>

            </div>

            <div className="calculation-row">

              <span>
                Marža / komad
              </span>

              <strong>
                {
                  calculations.marginValuePerPiece.toFixed(
                    2,
                  )
                }{" "}
                €
              </strong>

            </div>

            <div className="calculation-row highlight">

              <span>
                Prodajna cijena / komad
              </span>

              <strong>
                {
                  calculations.sellingPricePerPiece.toFixed(
                    2,
                  )
                }{" "}
                €
              </strong>

            </div>

            <div className="calculation-row highlight">

              <span>
                Količina
              </span>

              <strong>
                {position.quantity}
              </strong>

            </div>

            <div className="calculation-row highlight">

              <span>
                Ukupna prodajna vrijednost
              </span>

              <strong>
                {
                  calculations.totalSellingPrice.toFixed(
                    2,
                  )
                }{" "}
                €
              </strong>

            </div>

          </div>

          {/* ====================================== */}
          {/* OSTALI TROŠKOVI */}
          {/* ====================================== */}

          <div className="form-card other-costs-card">
            <h3>Ostali troškovi</h3>
            <p style={{ fontSize: "12px", color: "#64748b", marginTop: "-8px" }}>
              Iznosi se unose po komadu.
            </p>

            <label>
              Ostali trošak 1 — opis
              <input
                type="text"
                value={position.otherCost1Description}
                placeholder="npr. Dodatna obrada"
                onChange={(event) => updatePosition({ otherCost1Description: event.target.value })}
              />
            </label>
            <label>
              Ostali trošak 1 — iznos / komad (€)
              <LocalizedNumberInput
                min="0"
                step="0.01"
                value={position.otherCost1Amount}
                onValueChange={(value) => updatePosition({ otherCost1Amount: Math.max(0, value) })}
              />
            </label>

            <label>
              Ostali trošak 2 — opis
              <input
                type="text"
                value={position.otherCost2Description}
                placeholder="npr. Pakovanje"
                onChange={(event) => updatePosition({ otherCost2Description: event.target.value })}
              />
            </label>
            <label>
              Ostali trošak 2 — iznos / komad (€)
              <LocalizedNumberInput
                min="0"
                step="0.01"
                value={position.otherCost2Amount}
                onValueChange={(value) => updatePosition({ otherCost2Amount: Math.max(0, value) })}
              />
            </label>

            <label>
              Broj Ensata po komadu
              <LocalizedNumberInput
                min="0"
                step="1"
                value={position.ensatCount}
                onValueChange={(value) => updatePosition({ ensatCount: Math.max(0, Math.floor(value || 0)) })}
              />
            </label>

            <div className="calculation-row">
              <span>Cijena Ensata</span>
              <strong>{(Number(generalSettings.ensatPricePerPiece) || 0).toFixed(2)} €/kom.</strong>
            </div>
            <div className="calculation-row highlight">
              <span>Trošak Ensata / komad</span>
              <strong>{calculations.ensatCostPerPiece.toFixed(2)} €</strong>
            </div>
            <div className="calculation-row">
              <span>Ukupno ostali troškovi / komad</span>
              <strong>{calculations.otherCostsTotal.toFixed(2)} €</strong>
            </div>
          </div>

          {/* TRANSPORT */}
          <div className="form-card transport-option-card">
            <h3>Transport – ova pozicija</h3>
            <p style={{ fontSize: "12px", color: "#64748b", marginTop: "-8px" }}>
              Postavke transporta važe samo za poziciju {position.positionNumber || selectedIndex + 1}.
            </p>
            <label>
              Obračun transporta
              <select
                value={(typeof position.transportIncluded === "boolean" ? position.transportIncluded : transportIncluded) ? "included" : "excluded"}
                onChange={(event) => updatePosition({ transportIncluded: event.target.value === "included" })}
              >
                <option value="included">Sa transportom</option>
                <option value="excluded">Bez transporta</option>
              </select>
            </label>
            <label>
              Cijena transporta (€/kg)
              <LocalizedNumberInput
                min="0"
                step="0.01"
                value={Number.isFinite(Number(position.transportPricePerKg))
                  ? Number(position.transportPricePerKg)
                  : Number(generalSettings.transportPricePerKg) || 0}
                onValueChange={(value) => updatePosition({ transportPricePerKg: Math.max(0, value) })}
              />
            </label>
            <button
              type="button"
              className="secondary-button"
              style={{ marginTop: "6px" }}
              onClick={() => updatePosition({ transportPricePerKg: undefined })}
            >
              Vrati cijenu iz općih postavki
            </button>
            <div className="calculation-row highlight">
              <span>Transport / komad</span>
              <strong>{calculations.transportCost.toFixed(2)} €</strong>
            </div>
            {!(typeof position.transportIncluded === "boolean" ? position.transportIncluded : transportIncluded) && (
              <p className="transport-excluded-note" role="status">Transport nije uključen u trošak ni prodajnu cijenu ove pozicije.</p>
            )}
          </div>

        </div>

        {/* ====================================== */}
        {/* REZULTAT */}
        {/* ====================================== */}

        <div className="calculation-summary">

          <div>

            <span>
              Materijal
            </span>

            <strong>
              {
                calculations.materialCost.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

          <div>

            <span>
              Programiranje – ukupno
            </span>

            <strong>
              {
                calculations.programmingSeriesCost.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

          <div>

            <span>
              Priprema – ukupno
            </span>

            <strong>
              {
                calculations.setupSeriesCost.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

          <div>

            <span>
              Kontrola / komad
            </span>

            <strong>
              {calculations.inspectionCost.toFixed(2)}{" "}
              €
            </strong>

          </div>

          <div>

            <span>
              Transport / komad
            </span>

            <strong>
              {calculations.transportCost.toFixed(2)}{" "}
              €
            </strong>

          </div>

          <div>

            <span>
              Izrada
            </span>

            <strong>
              {
                calculations.machiningCost.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

          {calculations.surfaceProtectionCost > 0 && (
            <div>
              <span>Površinska zaštita / komad</span>
              <strong>{calculations.surfaceProtectionCost.toFixed(2)} €</strong>
            </div>
          )}

          {calculations.heatTreatmentCost > 0 && (
            <div>
              <span>Termička obrada / komad</span>
              <strong>{calculations.heatTreatmentCost.toFixed(2)} €</strong>
            </div>
          )}

          {calculations.otherOperationCost > 0 && (
            <div>
              <span>Ostali zahvat / komad</span>
              <strong>{calculations.otherOperationCost.toFixed(2)} €</strong>
            </div>
          )}

          {calculations.ensatCostPerPiece > 0 && (
            <div>
              <span>Ensati / komad</span>
              <strong>{calculations.ensatCostPerPiece.toFixed(2)} €</strong>
            </div>
          )}

          {calculations.otherCostsTotal > 0 && (
            <div>
              <span>Ostali troškovi / komad</span>
              <strong>{calculations.otherCostsTotal.toFixed(2)} €</strong>
            </div>
          )}

          <div className="total">

            <span>
              Trošak / komad
            </span>

            <strong>
              {
                calculations.totalCostPerPiece.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

        </div>

        {/* ====================================== */}
        {/* PRODAJNA CIJENA */}
        {/* ====================================== */}

        <div
          className="calculation-summary"
          style={{
            marginTop: "12px",
          }}
        >

          <div>

            <span>
              Marža
            </span>

            <strong>
              {
                calculations.safeMarginPercent.toFixed(
                  2,
                )
              }
              %
            </strong>

          </div>

          <div>

            <span>
              Marža / komad
            </span>

            <strong>
              {
                calculations.marginValuePerPiece.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

          <div className="total">

            <span>
              Prodajna cijena / komad
            </span>

            <strong>
              {
                calculations.sellingPricePerPiece.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

          <div>

            <span>
              Količina
            </span>

            <strong>
              {position.quantity}
            </strong>

          </div>

                    <div className="total">

            <span>
              UKUPNA PRODAJNA VRIJEDNOST
            </span>

            <strong>
              {
                calculations.totalSellingPrice.toFixed(
                  2,
                )
              }{" "}
              €
            </strong>

          </div>

        </div>

        {staffelPricesEnabled && (
          <section className="staffel-preview-panel" aria-label="Staffelpreise za odabranu poziciju">
            <div className="staffel-preview-heading">
              <div>
                <span className="staffel-kicker">PREGLED CIJENA</span>
                <h3>Staffelpreise — pozicija {position.positionNumber} · {position.code || "bez šifre"}</h3>
                <p>Ove količinske cijene će biti sačuvane uz ponudu i uključene u PDF i Excel.</p>
              </div>
              <span className="staffel-preview-count">{positionStaffelPrices.length} količinskih nivoa</span>
            </div>

            {activePositionStaffelInputInvalid || activePositionStaffelQuantities.length === 0 ? (
              <p className="staffel-error">{staffelMode === "per-position" ? "Za ovu poziciju unesi Price Scale količine u gornjoj tabeli." : "Ispravi listu zajedničkih količina da bi se cijene mogle izračunati."}</p>
            ) : (
              <div className="staffel-table-scroll">
                <table className="staffel-prices-table">
                  <thead>
                    <tr>
                      <th>Količina</th>
                      <th>Trošak / kom. (interno)</th>
                      <th>Prodajna cijena / kom.</th>
                      <th>Ukupno za ovu količinu</th>
                    </tr>
                  </thead>
                  <tbody>
                    {positionStaffelPrices.map((tier) => (
                      <tr key={tier.quantity}>
                        <td><strong>{tier.quantity} kom.</strong></td>
                        <td>{formatProfitabilityMoney(tier.totalCostPerPiece)}</td>
                        <td className="staffel-price-value">{formatProfitabilityMoney(tier.sellingPricePerPiece)}</td>
                        <td><strong>{formatProfitabilityMoney(tier.totalSellingPrice)}</strong></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </section>
        )}

        {/* ====================================== */}
        {/* KONTROLA PROFITABILNOSTI */}
        {/* ====================================== */}

        <section className="profitability-section" aria-labelledby="profitability-heading">
          <div className="profitability-section-header">
            <div>
              <h3 id="profitability-heading">Kontrola profitabilnosti</h3>
              <p>
                Pregled rezultata prema troškovima i prodajnim cijenama iz trenutne kalkulacije.
              </p>
            </div>
            <span className="profitability-badge">Informativni pregled</span>
          </div>

          <div className="profitability-grid">
            <article className="profitability-card">
              <span>Zarada / komad – odabrana pozicija</span>
              <strong className={calculations.marginValuePerPiece >= 0 ? "profit-positive" : "profit-negative"}>
                {formatProfitabilityMoney(calculations.sellingPricePerPiece - calculations.totalCostPerPiece)}
              </strong>
              <small>Prodajna cijena umanjena za evidentirani trošak.</small>
            </article>

            <article className="profitability-card">
              <span>Zarada za seriju – odabrana pozicija</span>
              <strong className={calculations.marginValuePerPiece >= 0 ? "profit-positive" : "profit-negative"}>
                {formatProfitabilityMoney((calculations.sellingPricePerPiece - calculations.totalCostPerPiece) * scenarioQuantity)}
              </strong>
              <small>Količina: {scenarioQuantity} kom.</small>
            </article>

            <article className="profitability-card">
              <span>Ukupni trošak ponude</span>
              <strong>{formatProfitabilityMoney(liveQuoteProfitability.totalCost)}</strong>
              <small>Zbir evidentiranih troškova svih pozicija.</small>
            </article>

            <article className="profitability-card">
              <span>Ukupna prodajna vrijednost</span>
              <strong>{formatProfitabilityMoney(liveQuoteProfitability.totalSellingPrice)}</strong>
              <small>Zbir prodajnih vrijednosti svih pozicija.</small>
            </article>

            <article className="profitability-card profitability-card-emphasis">
              <span>Očekivana zarada cijele ponude</span>
              <strong className={liveQuoteProfitability.totalProfit >= 0 ? "profit-positive" : "profit-negative"}>
                {formatProfitabilityMoney(liveQuoteProfitability.totalProfit)}
              </strong>
              <small>Prodajna vrijednost minus evidentirani troškovi.</small>
            </article>

            <article className="profitability-card">
              <span>Zarada kao dio prodajne vrijednosti</span>
              <strong className={liveQuoteProfitability.totalProfit >= 0 ? "profit-positive" : "profit-negative"}>
                {liveQuoteProfitability.profitPercentOfSales.toLocaleString("bs-BA", {
                  minimumFractionDigits: 1,
                  maximumFractionDigits: 1,
                })}%
              </strong>
              <small>Ovo nije isto što i uvećanje troška odabrano kroz maržu.</small>
            </article>
          </div>

          <p className="profitability-disclaimer">
            Ovaj rezultat obuhvata samo troškove unesene i izračunate u aplikaciji. Ne uključuje druge režijske troškove ili poreze koji nisu uneseni u kalkulaciju.
          </p>

          <div className="profitability-simulation">
            <div className="profitability-simulation-header">
              <div>
                <h4>Simulacija promjene prodajne cijene</h4>
                <p>Promijeni probnu cijenu za trenutno odabranu poziciju i vidi uticaj na zaradu. Simulacija ne mijenja niti snima cijenu ponude.</p>
              </div>
              <button
                type="button"
                className="secondary-button"
                onClick={() => {
                  setScenarioSellingPrices((current) => {
                    const next = { ...current }
                    delete next[scenarioPriceKey]
                    return next
                  })
                }}
              >
                Vrati izračunatu cijenu
              </button>
            </div>

            <label className="profitability-simulation-input">
              Probna prodajna cijena / kom (€)
              <LocalizedNumberInput
                min="0"
                step="0.01"
                value={scenarioSellingPriceText}
                onValueChange={(value) =>
                  setScenarioSellingPrices((current) => ({
                    ...current,
                    [scenarioPriceKey]: formatLocalizedNumber(value),
                  }))
                }
                aria-label="Probna prodajna cijena po komadu za simulaciju"
              />
            </label>

            {scenarioSellingPriceIsValid ? (
              <div className="profitability-scenario-results" aria-live="polite">
                <div>
                  <span>Rezultat / komad</span>
                  <strong className={scenarioProfitPerPiece >= 0 ? "profit-positive" : "profit-negative"}>
                    {formatProfitabilityMoney(scenarioProfitPerPiece)}
                  </strong>
                </div>
                <div>
                  <span>Rezultat serije</span>
                  <strong className={scenarioProfitForSeries >= 0 ? "profit-positive" : "profit-negative"}>
                    {formatProfitabilityMoney(scenarioProfitForSeries)}
                  </strong>
                </div>
                <div>
                  <span>Promjena zarade na seriji</span>
                  <strong className={scenarioPriceDeltaPerPiece >= 0 ? "profit-positive" : "profit-negative"}>
                    {scenarioPriceDeltaPerPiece > 0 ? "+" : scenarioPriceDeltaPerPiece < 0 ? "−" : ""}
                    {formatProfitabilityMoney(Math.abs(scenarioPriceDeltaPerPiece * scenarioQuantity))}
                  </strong>
                </div>
                <div className="profitability-scenario-total">
                  <span>Rezultat cijele ponude s probnom cijenom</span>
                  <strong className={scenarioQuoteProfit >= 0 ? "profit-positive" : "profit-negative"}>
                    {formatProfitabilityMoney(scenarioQuoteProfit)}
                  </strong>
                </div>
              </div>
            ) : (
              <p className="profitability-input-hint">Unesi važeću cijenu od 0 € ili više da prikažeš simulaciju.</p>
            )}
          </div>
        </section>

      </section>

    </div>
  )
}

export default NewQuote