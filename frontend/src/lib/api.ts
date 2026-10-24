import { apiClient, ensureCsrfCookie, streamRequest } from './api-client'
import type {
  Asset, AssetInput, AssistantMessage, AssistantStreamEvent, CursorCollection, Dashboard, Membership, Portfolio, PortfolioInput,
  Resource, Role, Transaction, TransactionInput, User,
} from './api-types'

const v1 = '/api/v1'
const query = (values: Record<string, string | number | undefined | null>) => {
  const params = new URLSearchParams()
  Object.entries(values).forEach(([key, value]) => { if (value !== undefined && value !== null && value !== '') params.set(key, String(value)) })
  const string = params.toString()
  return string ? `?${string}` : ''
}

export const authApi = {
  user: () => apiClient.get<Resource<User>>(`${v1}/user`),
  login: async (input: { email: string; password: string; remember?: boolean }) => {
    await ensureCsrfCookie()
    return apiClient.post<Resource<User>>(`${v1}/auth/login`, input)
  },
  register: async (input: { name: string; email: string; password: string; password_confirmation: string }) => {
    await ensureCsrfCookie()
    return apiClient.post<Resource<User>>(`${v1}/auth/register`, input)
  },
  logout: () => apiClient.post<void>(`${v1}/auth/logout`),
}

export const portfoliosApi = {
  list: (cursor?: string | null) => apiClient.get<CursorCollection<Portfolio>>(`${v1}/portfolios${query({ cursor })}`),
  get: (id: number) => apiClient.get<Resource<Portfolio>>(`${v1}/portfolios/${id}`),
  create: (input: PortfolioInput) => apiClient.post<Resource<Portfolio>>(`${v1}/portfolios`, input),
  update: (id: number, input: Partial<PortfolioInput>) => apiClient.patch<Resource<Portfolio>>(`${v1}/portfolios/${id}`, input),
  delete: (id: number) => apiClient.delete<void>(`${v1}/portfolios/${id}`),
  dashboard: (id: number) => apiClient.get<Resource<Dashboard>>(`${v1}/portfolios/${id}/dashboard`),
}

export const assetsApi = {
  list: (portfolioId: number, params: { cursor?: string | null; type?: string; include_archived?: string } = {}) =>
    apiClient.get<CursorCollection<Asset>>(`${v1}/portfolios/${portfolioId}/assets${query(params)}`),
  create: (portfolioId: number, input: AssetInput) => apiClient.post<Resource<Asset>>(`${v1}/portfolios/${portfolioId}/assets`, input),
  update: (portfolioId: number, id: number, input: Partial<AssetInput>) => apiClient.patch<Resource<Asset>>(`${v1}/portfolios/${portfolioId}/assets/${id}`, input),
  archive: (portfolioId: number, id: number) => apiClient.delete<void>(`${v1}/portfolios/${portfolioId}/assets/${id}`),
}

export const transactionsApi = {
  list: (portfolioId: number, params: { cursor?: string | null; asset_id?: number; kind?: string; occurred_from?: string; occurred_to?: string } = {}) =>
    apiClient.get<CursorCollection<Transaction>>(`${v1}/portfolios/${portfolioId}/transactions${query(params)}`),
  create: (portfolioId: number, assetId: number, input: TransactionInput) => apiClient.post<Resource<Transaction>>(`${v1}/portfolios/${portfolioId}/assets/${assetId}/transactions`, input),
  reverse: (portfolioId: number, id: number, reason: string) => apiClient.post<Resource<Transaction>>(`${v1}/portfolios/${portfolioId}/transactions/${id}/reverse`, { reason }),
}

export const membersApi = {
  list: (portfolioId: number) => apiClient.get<Resource<Membership[]>>(`${v1}/portfolios/${portfolioId}/members`),
  create: (portfolioId: number, input: { email: string; role: Role }) => apiClient.post<Resource<Membership>>(`${v1}/portfolios/${portfolioId}/members`, input),
  update: (portfolioId: number, id: number, role: Role) => apiClient.patch<Resource<Membership>>(`${v1}/portfolios/${portfolioId}/members/${id}`, { role }),
  delete: (portfolioId: number, id: number) => apiClient.delete<void>(`${v1}/portfolios/${portfolioId}/members/${id}`),
}

export const assistantApi = {
  chat: async (
    portfolioId: number,
    messages: AssistantMessage[],
    onEvent: (event: AssistantStreamEvent) => void,
    signal?: AbortSignal,
  ) => {
    const response = await streamRequest(`${v1}/portfolios/${portfolioId}/assistant/chat`, { messages }, signal)
    if (!response.body) throw new Error('The assistant response could not be streamed.')

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (true) {
      const { done, value } = await reader.read()
      buffer += decoder.decode(value, { stream: !done }).replace(/\r\n/g, '\n')
      const frames = buffer.split('\n\n')
      buffer = frames.pop() ?? ''

      for (const frame of frames) {
        const parsed = parseAssistantEvent(frame)
        if (parsed) onEvent(parsed)
      }

      if (done) break
    }

    if (buffer.trim()) {
      const parsed = parseAssistantEvent(buffer)
      if (parsed) onEvent(parsed)
    }
  },
}

function parseAssistantEvent(frame: string): AssistantStreamEvent | null {
  let eventName = ''
  const dataLines: string[] = []

  for (const line of frame.split('\n')) {
    if (line.startsWith('event:')) eventName = line.slice(6).trim()
    if (line.startsWith('data:')) dataLines.push(line.slice(5).trimStart())
  }

  if (!eventName || eventName === 'update' || dataLines.length === 0) return null

  try {
    const data = JSON.parse(dataLines.join('\n')) as AssistantStreamEvent['data']
    return { event: eventName, data } as AssistantStreamEvent
  } catch {
    return null
  }
}
