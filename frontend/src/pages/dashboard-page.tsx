import { useQuery } from '@tanstack/react-query'
import { BarChart3, Clock3, WalletCards } from 'lucide-react'
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts'
import { useOutletContext } from 'react-router-dom'
import { ErrorState, PageLoading } from '../components/page-states'
import { Badge } from '../components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../components/ui/table'
import { portfoliosApi } from '../lib/api'
import { assetTypeLabel, money, quantity, shortDate } from '../lib/format'
import type { PortfolioOutletContext } from './portfolio-layout'

const colors = ['#276b58', '#557a8d', '#9a7650', '#89958d']

export function DashboardPage() {
  const { portfolio } = useOutletContext<PortfolioOutletContext>()
  const dashboard = useQuery({ queryKey: ['portfolio', portfolio.id, 'dashboard'], queryFn: () => portfoliosApi.dashboard(portfolio.id).then((response) => response.data) })
  if (dashboard.isLoading) return <PageLoading />
  if (dashboard.error || !dashboard.data) return <ErrorState error={dashboard.error} />
  const data = dashboard.data
  return <div className="space-y-6">
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Card><CardContent className="p-5"><div className="flex items-center gap-2 text-sm text-[#687871]"><WalletCards size={16} />Total portfolio value</div><p className="tabular mt-3 text-3xl font-semibold tracking-tight text-[#17261f]">{money(data.total_value, portfolio.base_currency)}</p></CardContent></Card>
      <Card><CardContent className="p-5"><div className="flex items-center gap-2 text-sm text-[#687871]"><BarChart3 size={16} />Tracked assets</div><p className="tabular mt-3 text-3xl font-semibold text-[#17261f]">{data.assets.length}</p></CardContent></Card>
      <Card><CardContent className="p-5"><div className="flex items-center gap-2 text-sm text-[#687871]"><Clock3 size={16} />Valuation basis</div><p className="mt-3 text-base font-semibold text-[#263a32]">Manual prototype prices</p><p className="mt-1 text-xs text-[#718079]">As of {shortDate(data.valued_at)}</p></CardContent></Card>
    </div>
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.65fr)]">
      <Card><CardHeader><CardTitle>Assets</CardTitle></CardHeader><CardContent className="px-0 pb-0"><Table><TableHeader><TableRow><TableHead>Asset</TableHead><TableHead>Type</TableHead><TableHead className="text-right">Quantity</TableHead><TableHead className="text-right">Value</TableHead></TableRow></TableHeader><TableBody>{data.assets.length ? data.assets.map((asset) => <TableRow key={asset.asset_id}><TableCell><p className="font-medium text-[#22372e]">{asset.name}</p><p className="text-xs text-[#75827c]">Manual price · {shortDate(asset.price_as_of)}</p></TableCell><TableCell><Badge>{assetTypeLabel[asset.type]}</Badge></TableCell><TableCell className="tabular text-right">{quantity(asset.quantity)}</TableCell><TableCell className="tabular text-right font-medium">{money(asset.value, portfolio.base_currency)}</TableCell></TableRow>) : <TableRow><TableCell colSpan={4} className="py-12 text-center text-[#718079]">No valued assets yet.</TableCell></TableRow>}</TableBody></Table></CardContent></Card>
      <Card><CardHeader><CardTitle>Allocation</CardTitle></CardHeader><CardContent>{data.allocation_by_type.length ? <><div className="h-56"><ResponsiveContainer width="100%" height="100%"><PieChart><Pie data={data.allocation_by_type} dataKey="value" nameKey="type" innerRadius={58} outerRadius={88} paddingAngle={2}>{data.allocation_by_type.map((entry, index) => <Cell key={entry.type} fill={colors[index % colors.length] ?? colors[0]} />)}</Pie><Tooltip formatter={(value) => money(String(value), portfolio.base_currency)} /></PieChart></ResponsiveContainer></div><div className="space-y-3">{data.allocation_by_type.map((entry, index) => <div key={entry.type} className="flex items-center justify-between text-sm"><div className="flex items-center gap-2"><span className="h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: colors[index % colors.length] ?? colors[0] }} /><span className="text-[#52635b]">{assetTypeLabel[entry.type]}</span></div><span className="tabular font-medium">{Number(entry.percentage).toFixed(1)}%</span></div>)}</div></> : <div className="flex h-56 items-center justify-center text-sm text-[#718079]">Allocation appears after assets have value.</div>}</CardContent></Card>
    </div>
  </div>
}
