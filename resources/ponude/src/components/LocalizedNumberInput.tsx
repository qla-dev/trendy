import { useEffect, useRef, useState, type FocusEvent, type InputHTMLAttributes, type KeyboardEvent } from "react"
import {
  evaluateArithmeticExpression,
  formatLocalizedNumber,
  parseLocalizedNumber,
} from "../utils/numericInput"

type NativeInputProps = Omit<
  InputHTMLAttributes<HTMLInputElement>,
  "type" | "value" | "onChange" | "min" | "max" | "step"
>

export type LocalizedNumberInputProps = NativeInputProps & {
  value: number | string | null | undefined
  onValueChange: (value: number) => void
  min?: number | string
  max?: number | string
  step?: number | string
  allowFormula?: boolean
}

function displayValue(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === "") return ""
  if (typeof value === "number") return formatLocalizedNumber(value)

  const parsed = parseLocalizedNumber(value)
  return parsed === null ? value : formatLocalizedNumber(parsed)
}

export function LocalizedNumberInput({
  value,
  onValueChange,
  min,
  max,
  step: _step,
  allowFormula = false,
  className,
  onFocus,
  onBlur,
  onKeyDown,
  ...inputProps
}: LocalizedNumberInputProps) {
  const [draft, setDraft] = useState(() => displayValue(value))
  const [focused, setFocused] = useState(false)
  const [validationMessage, setValidationMessage] = useState("")
  const suppressNextBlurCommit = useRef(false)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (!focused && !validationMessage) setDraft(displayValue(value))
  }, [value, focused, validationMessage])

  useEffect(() => {
    inputRef.current?.setCustomValidity(validationMessage)
  }, [validationMessage])

  void _step
  const minimum = min === undefined || min === "" ? null : Number(min)
  const maximum = max === undefined || max === "" ? null : Number(max)

  function constrain(parsed: number): number | null {
    if (!Number.isFinite(parsed)) return null
    if (minimum !== null && Number.isFinite(minimum) && parsed < minimum) {
      setValidationMessage(`Minimalna vrijednost je ${formatLocalizedNumber(minimum)}.`)
      return null
    }
    if (maximum !== null && Number.isFinite(maximum) && parsed > maximum) {
      setValidationMessage(`Maksimalna vrijednost je ${formatLocalizedNumber(maximum)}.`)
      return null
    }
    return parsed
  }

  function commit(raw: string): boolean {
    const trimmed = raw.trim()
    if (!trimmed) {
      onValueChange(0)
      setDraft("")
      setValidationMessage("")
      return true
    }

    let parsed: number | null = null
    try {
      parsed = allowFormula
        ? evaluateArithmeticExpression(trimmed)
        : parseLocalizedNumber(trimmed)
    } catch {
      parsed = null
    }

    if (parsed === null) {
      setValidationMessage(allowFormula
        ? "Neispravan izraz. Koristi +, −, *, / i zagrade."
        : "Unesi ispravan decimalni broj, npr. 12,5.")
      return false
    }

    const constrained = constrain(parsed)
    if (constrained === null) return false

    onValueChange(constrained)
    setDraft(formatLocalizedNumber(constrained))
    setValidationMessage("")
    return true
  }

  function handleBlur(event: FocusEvent<HTMLInputElement>) {
    setFocused(false)
    if (suppressNextBlurCommit.current) {
      suppressNextBlurCommit.current = false
    } else {
      commit(event.currentTarget.value)
    }
    onBlur?.(event)
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Enter") {
      event.preventDefault()
      const committed = commit(event.currentTarget.value)
      if (committed) {
        suppressNextBlurCommit.current = true
        event.currentTarget.blur()
      }
    }
    onKeyDown?.(event)
  }

  return (
    <>
      <input
        {...inputProps}
        ref={inputRef}
        type="text"
        inputMode="decimal"
        autoComplete="off"
        value={draft}
        className={`${className ?? ""} localized-number-input${validationMessage ? " localized-number-input-invalid" : ""}`.trim()}
        aria-invalid={validationMessage ? true : inputProps["aria-invalid"]}
        title={validationMessage || inputProps.title}
        onFocus={(event) => {
          setFocused(true)
          setValidationMessage("")
          onFocus?.(event)
        }}
        onChange={(event) => {
          const next = event.target.value
          setDraft(next)
          setValidationMessage("")

          if (!allowFormula) {
            const parsed = parseLocalizedNumber(next)
            if (parsed !== null) {
              const constrained = constrain(parsed)
              if (constrained !== null) onValueChange(constrained)
            } else if (next.trim() === "") {
              onValueChange(0)
            }
          }
        }}
        onBlur={handleBlur}
        onKeyDown={handleKeyDown}
      />
      {validationMessage && (
        <span className="localized-number-input-error" role="alert">
          {validationMessage}
        </span>
      )}
    </>
  )
}
