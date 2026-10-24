import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, useContext, type ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { authApi } from '../../lib/api'
import { ApiError } from '../../lib/api-client'
import type { User } from '../../lib/api-types'
import { ErrorState, PageLoading } from '../../components/page-states'

interface AuthContextValue {
  user: User | null
  setUser: (user: User | null) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const userQuery = useQuery({
    queryKey: ['auth', 'user'],
    queryFn: () => authApi.user().then((response) => response.data),
    retry: (count, error) => !(error instanceof ApiError && error.status === 401) && count < 1,
  })

  const user = userQuery.error instanceof ApiError && userQuery.error.status === 401 ? null : (userQuery.data ?? null)
  const setUser = (next: User | null) => queryClient.setQueryData(['auth', 'user'], next)

  if (userQuery.isLoading) return <div className="mx-auto max-w-6xl p-6"><PageLoading /></div>
  if (userQuery.error && !(userQuery.error instanceof ApiError && userQuery.error.status === 401)) return <div className="mx-auto max-w-xl p-6"><ErrorState error={userQuery.error} /></div>
  return <AuthContext.Provider value={{ user, setUser }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside AuthProvider')
  return context
}

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const location = useLocation()
  return user ? children : <Navigate to="/login" replace state={{ from: location.pathname }} />
}

export function GuestRoute({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  return user ? <Navigate to="/portfolios" replace /> : children
}
