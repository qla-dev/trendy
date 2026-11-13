export interface QuoteExportCostSource {
  quantity?: unknown
  programmingMinutes?: unknown
  programmingHourlyRate?: unknown
  pricingSnapshot?: { programmingHourlyRate?: unknown } | null
  programmingCost?: unknown
  setupMinutes?: unknown
  machineSetupRate?: unknown
  setupHourlyRate?: unknown
  setupCost?: unknown
  totalCostPerPiece?: unknown
  sellingPricePerPiece?: unknown
  marginAmount?: unknown
  marginValuePerPiece?: unknown
}

export interface QuoteExportCostBreakdown {
  /** Full one-time programming + machine preparation cost for the position/series. */
  rustCostTotal: number
  /** Remaining per-piece costs plus the complete per-piece margin. */
  stuckCostPerPiece: number
}

function finiteNumber(value: unknown): number | null {
  if (value === undefined || value === null || value === "") return null
  const number = Number(value)
  return Number.isFinite(number) ? number : null
}

/**
 * Splits the saved selling price into the customer's requested quotation form:
 * - Rüstkosten: full series programming + setup cost (not divided by quantity)
 * - Stückkosten: all other per-piece costs plus the full per-piece margin
 *
 * For Staffelpreise the same one-time Rüstkosten are shown on each tier row;
 * the per-piece share is recalculated by dividing that total by the tier quantity.
 * This keeps Rüstkosten / tier quantity + Stückkosten equal to the saved selling
 * price per piece.
 */
export function getQuoteExportCostBreakdown(
  position: QuoteExportCostSource,
  targetQuantity: unknown,
  tierTotalCostPerPiece?: unknown,
  tierMarginValuePerPiece?: unknown,
): QuoteExportCostBreakdown {
  const baseQuantity = Math.max(1, finiteNumber(position.quantity) ?? 1)
  const quantity = Math.max(1, finiteNumber(targetQuantity) ?? baseQuantity)

  const programmingMinutes = finiteNumber(position.programmingMinutes)
  const programmingRate = finiteNumber(position.programmingHourlyRate) ?? finiteNumber(position.pricingSnapshot?.programmingHourlyRate) ?? 35
  const savedProgrammingCostPerPiece = finiteNumber(position.programmingCost)
  const programmingSeriesCost = programmingMinutes !== null && (programmingMinutes > 0 || savedProgrammingCostPerPiece === null)
    ? Math.max(0, programmingMinutes) / 60 * Math.max(0, programmingRate)
    : Math.max(0, savedProgrammingCostPerPiece ?? 0) * baseQuantity

  const setupMinutes = finiteNumber(position.setupMinutes)
  const setupRate = finiteNumber(position.machineSetupRate) ?? finiteNumber(position.setupHourlyRate)
  const savedSetupCostPerPiece = finiteNumber(position.setupCost)
  const setupSeriesCost = setupMinutes !== null && setupRate !== null && (setupMinutes > 0 || savedSetupCostPerPiece === null)
    ? Math.max(0, setupMinutes) / 60 * Math.max(0, setupRate)
    : Math.max(0, savedSetupCostPerPiece ?? 0) * baseQuantity

  const rawTotalCost = tierTotalCostPerPiece === undefined
    ? finiteNumber(position.totalCostPerPiece)
    : finiteNumber(tierTotalCostPerPiece)
  const totalCostPerPiece = rawTotalCost !== null
    ? Math.max(0, rawTotalCost)
    : Math.max(
        0,
        (finiteNumber(position.sellingPricePerPiece) ?? 0) -
          (finiteNumber(position.marginAmount) ?? 0),
      )

  const rawMarginPerPiece = tierMarginValuePerPiece === undefined
    ? finiteNumber(position.marginValuePerPiece) ?? finiteNumber(position.marginAmount)
    : finiteNumber(tierMarginValuePerPiece)
  const marginPerPiece = rawMarginPerPiece !== null
    ? Math.max(0, rawMarginPerPiece)
    : Math.max(
        0,
        (finiteNumber(position.sellingPricePerPiece) ?? 0) - totalCostPerPiece,
      )

  const rustCostTotal = programmingSeriesCost + setupSeriesCost
  const rustCostSharePerPiece = rustCostTotal / quantity

  return {
    rustCostTotal,
    // The margin is intentionally allocated to Stückkosten so that the
    // customer's unit price remains equal to the application's saved selling price.
    stuckCostPerPiece: totalCostPerPiece - rustCostSharePerPiece + marginPerPiece,
  }
}
