import { lazy, Suspense } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { AppShell } from './components/app-shell'
import { PageLoading } from './components/page-states'
import { GuestRoute, ProtectedRoute } from './features/auth/auth-context'

const LoginPage = lazy(() => import('./pages/auth-pages').then((module) => ({ default: module.LoginPage })))
const RegisterPage = lazy(() => import('./pages/auth-pages').then((module) => ({ default: module.RegisterPage })))
const PortfolioListPage = lazy(() => import('./pages/portfolio-list-page').then((module) => ({ default: module.PortfolioListPage })))
const PortfolioLayout = lazy(() => import('./pages/portfolio-layout').then((module) => ({ default: module.PortfolioLayout })))
const DashboardPage = lazy(() => import('./pages/dashboard-page').then((module) => ({ default: module.DashboardPage })))
const AssetsPage = lazy(() => import('./pages/assets-page').then((module) => ({ default: module.AssetsPage })))
const TransactionsPage = lazy(() => import('./pages/transactions-page').then((module) => ({ default: module.TransactionsPage })))
const MembersPage = lazy(() => import('./pages/members-page').then((module) => ({ default: module.MembersPage })))

export default function App() {
  return <Suspense fallback={<div className="mx-auto max-w-7xl p-6"><PageLoading /></div>}><Routes>
    <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
    <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />
    <Route element={<ProtectedRoute><AppShell /></ProtectedRoute>}>
      <Route path="/portfolios" element={<PortfolioListPage />} />
      <Route path="/portfolios/:portfolioId" element={<PortfolioLayout />}>
        <Route index element={<DashboardPage />} />
        <Route path="assets" element={<AssetsPage />} />
        <Route path="transactions" element={<TransactionsPage />} />
        <Route path="members" element={<MembersPage />} />
      </Route>
    </Route>
    <Route path="*" element={<Navigate to="/portfolios" replace />} />
  </Routes></Suspense>
}
