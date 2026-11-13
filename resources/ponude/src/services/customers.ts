import { api } from "../api"

export interface Customer {
  id?: string
  naziv: string
  adresa?: string
  postanskiBroj?: string
  grad?: string
  drzava?: string
  kontaktOsoba?: string
  email?: string
  telefon?: string
  aktivan?: boolean
  napomena?: string
  createdAt?: Date | any
  updatedAt?: Date | any
}

function normalize(c: any): Customer {
  return {
    id: String(c.id ?? ""),
    naziv: c.naziv ?? "",
    adresa: c.adresa,
    postanskiBroj: c.postanski_broj ?? c.postanskiBroj,
    grad: c.grad,
    drzava: c.drzava,
    kontaktOsoba: c.kontakt_osoba ?? c.kontaktOsoba,
    email: c.email,
    telefon: c.telefon,
    aktivan: c.aktivan,
    napomena: c.napomena,
    createdAt: c.created_at,
    updatedAt: c.updated_at,
  }
}

function toApi(c: Partial<Customer>): Record<string, unknown> {
  return {
    naziv: c.naziv,
    adresa: c.adresa,
    postanski_broj: c.postanskiBroj,
    grad: c.grad,
    drzava: c.drzava,
    kontakt_osoba: c.kontaktOsoba,
    email: c.email,
    telefon: c.telefon,
    aktivan: c.aktivan,
    napomena: c.napomena,
  }
}

export async function addCustomer(customer: Customer) {
  const result = await api.post<any>('/customers', toApi(customer))
  return String(result.id)
}

export async function getCustomers(): Promise<Customer[]> {
  const data = await api.get<any[]>('/customers')
  return data.map(normalize).sort((a, b) => (a.naziv || "").localeCompare(b.naziv || "", "bs"))
}

export async function getActiveCustomers(): Promise<Customer[]> {
  const customers = await getCustomers()
  return customers.filter((c) => c.aktivan !== false)
}

export async function updateCustomer(id: string | number, customer: Partial<Customer>) {
  await api.put(`/customers/${id}`, toApi(customer))
}

export async function deleteCustomer(id: string | number) {
  await api.delete(`/customers/${id}`)
}
