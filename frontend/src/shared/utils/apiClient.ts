const API_BASE_URL = import.meta.env.VITE_API_URL

export const apiFetch = async (endpoint: string, options?: RequestInit) => {
  const token = localStorage.getItem('access_token')

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(token && { Authorization: `Bearer ${token}` }),
    ...options?.headers,
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  })

  return response
}

export const apiGet = (endpoint: string) => apiFetch(endpoint)
export const apiPost = (endpoint: string, body?: any) =>
  apiFetch(endpoint, { method: 'POST', body: body ? JSON.stringify(body) : undefined })
export const apiPut = (endpoint: string, body?: any) =>
  apiFetch(endpoint, { method: 'PUT', body: body ? JSON.stringify(body) : undefined })
export const apiDelete = (endpoint: string) => apiFetch(endpoint, { method: 'DELETE' })
