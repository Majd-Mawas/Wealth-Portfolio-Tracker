export type Role = 'owner' | 'editor' | 'viewer'
export type AssetType = 'stock' | 'crypto' | 'real_estate' | 'cash'
export type WritableTransactionKind = 'buy' | 'sell' | 'deposit' | 'withdrawal' | 'adjustment'
export type TransactionKind = WritableTransactionKind | 'reversal'

export interface User {
  id: number
  name: string
  email: string
}

export interface PortfolioCapabilities {
  update_portfolio: boolean
  manage_members: boolean
  manage_assets: boolean
  record_transactions: boolean
  delete_portfolio: boolean
}

export interface Portfolio {
  id: number
  name: string
  base_currency: string
  role: Role
  capabilities: PortfolioCapabilities
  created_at: string
  updated_at: string
}

export interface Asset {
  id: number
  portfolio_id: number
  type: AssetType
  name: string
  symbol: string | null
  currency: string
  current_price: string
  price_as_of: string
  quantity: string
  value: string
  metadata: Record<string, unknown> | null
  archived_at: string | null
  created_at: string
  updated_at: string
}

export interface Transaction {
  id: number
  asset: Pick<Asset, 'id' | 'name' | 'type' | 'symbol'>
  kind: TransactionKind
  quantity_delta: string
  unit_price: string | null
  fees: string
  occurred_at: string
  note: string | null
  source: 'manual' | 'reversal'
  reversal_of_id: number | null
  created_by: Pick<User, 'id' | 'name'>
  created_at: string
}

export interface Membership {
  id: number
  user: User
  role: Role
  created_at: string
}

export interface DashboardAllocation {
  type: AssetType
  value: string
  percentage: string
}

export interface DashboardAsset {
  asset_id: number
  name: string
  type: AssetType
  quantity: string
  current_price: string
  value: string
  percentage: string
  price_as_of: string
  price_status: 'manual' | 'seeded'
}

export interface Dashboard {
  portfolio: Pick<Portfolio, 'id' | 'name' | 'base_currency'>
  total_value: string
  valued_at: string
  allocation_by_type: DashboardAllocation[]
  assets: DashboardAsset[]
}

export interface Resource<T> { data: T }
export interface CursorCollection<T> {
  data: T[]
  meta: { next_cursor: string | null }
}

export interface ApiErrorBody {
  error: {
    code: string
    message: string
    fields?: Record<string, string[]>
    request_id?: string
  }
}

export interface PortfolioInput { name: string; base_currency: string }
export interface AssetInput {
  type: AssetType
  name: string
  symbol?: string | null
  currency: string
  current_price: string
  price_as_of: string
  metadata?: Record<string, unknown> | null
  opening?: {
    quantity: string
    unit_price?: string | null
    occurred_at: string
    note?: string | null
  }
}
export interface TransactionInput {
  kind: WritableTransactionKind
  quantity: string
  unit_price?: string | null
  fees: string
  occurred_at: string
  note?: string | null
}

export interface AssistantMessage {
  role: 'user' | 'assistant'
  content: string
}

export type AssistantStreamEvent =
  | { event: 'meta'; data: { request_id: string } }
  | { event: 'text_delta'; data: { text: string } }
  | { event: 'tool_started' | 'tool_completed'; data: { tool: string } }
  | { event: 'tool_error'; data: { tool: string; code: string; message: string; fields?: Record<string, string[]> } }
  | { event: 'error'; data: { code: string; message: string; request_id?: string; retryable: boolean } }
  | { event: 'done'; data: { finish_reason: 'stop' | 'error' } }
