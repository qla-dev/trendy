const API_BASE = '/api/cnc'

function getToken(): string | null {
  return localStorage.getItem('cnc_token')
}

export function setToken(token: string): void {
  localStorage.setItem('cnc_token', token)
}

export function clearToken(): void {
  localStorage.removeItem('cnc_token')
}

export function hasToken(): boolean {
  return !!getToken()
}

async function request<T = any>(
  method: string,
  path: string,
  body?: unknown,
): Promise<T> {
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  }

  const token = getToken()
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  const response = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (response.status === 401) {
    clearToken()
    window.location.href = '/ponude/login'
    throw new Error('Unauthorized')
  }

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}))
    throw new Error(errorData.message || errorData.error || `HTTP ${response.status}`)
  }

  return response.json()
}

export const api = {
  get: <T = any>(path: string) => request<T>('GET', path),
  post: <T = any>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T = any>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T = any>(path: string) => request<T>('DELETE', path),
}
