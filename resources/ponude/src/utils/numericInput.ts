/** Parses a localized decimal. Comma is the preferred decimal separator;
 * a dot remains accepted for compatibility with values pasted from systems
 * that use the English decimal notation. Thousands may be separated by dots
 * or whitespace when a decimal comma is also present (e.g. 1.234,56).
 */
export function parseLocalizedNumber(raw: string): number | null {
  const trimmed = raw.trim()
  if (!trimmed) return null

  let normalized = trimmed.replace(/[\s\u00a0\u202f]/g, "")
  if (normalized.includes(",")) {
    normalized = normalized.replace(/\./g, "").replace(",", ".")
  }

  if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(normalized)) return null
  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : null
}

/** Formats a number for Bosnian-style input fields without grouping digits. */
export function formatLocalizedNumber(value: number): string {
  if (!Number.isFinite(value)) return ""
  return String(value).replace(".", ",")
}

/**
 * Safely evaluates basic arithmetic without eval()/Function(). Supports
 * decimal comma or dot, unary +/- and parentheses, with normal operator
 * precedence. Returns a finite number or throws for invalid input.
 */
export function evaluateArithmeticExpression(raw: string): number {
  const source = raw.replace(/\s+/g, "").replace(/,/g, ".")
  if (!source || source.length > 120) throw new Error("Neispravan izraz.")

  let index = 0

  function parseNumber(): number {
    const match = source.slice(index).match(/^(?:\d+(?:\.\d*)?|\.\d+)/)
    if (!match) throw new Error("Očekivan je broj.")
    index += match[0].length
    const value = Number(match[0])
    if (!Number.isFinite(value)) throw new Error("Broj nije konačan.")
    return value
  }

  function parsePrimary(): number {
    if (source[index] === "(") {
      index += 1
      const value = parseExpression()
      if (source[index] !== ")") throw new Error("Nedostaje zatvorena zagrada.")
      index += 1
      return value
    }
    return parseNumber()
  }

  function parseUnary(): number {
    if (source[index] === "+") {
      index += 1
      return parseUnary()
    }
    if (source[index] === "-") {
      index += 1
      return -parseUnary()
    }
    return parsePrimary()
  }

  function parseTerm(): number {
    let value = parseUnary()
    while (source[index] === "*" || source[index] === "/") {
      const operator = source[index]
      index += 1
      const right = parseUnary()
      if (operator === "/" && right === 0) throw new Error("Dijeljenje nulom nije dozvoljeno.")
      value = operator === "*" ? value * right : value / right
      if (!Number.isFinite(value)) throw new Error("Rezultat nije konačan.")
    }
    return value
  }

  function parseExpression(): number {
    let value = parseTerm()
    while (source[index] === "+" || source[index] === "-") {
      const operator = source[index]
      index += 1
      const right = parseTerm()
      value = operator === "+" ? value + right : value - right
      if (!Number.isFinite(value)) throw new Error("Rezultat nije konačan.")
    }
    return value
  }

  const result = parseExpression()
  if (index !== source.length) throw new Error("Izraz sadrži nedozvoljene znakove.")
  return result
}
