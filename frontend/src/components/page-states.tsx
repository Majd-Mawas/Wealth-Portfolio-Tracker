import { Inbox } from 'lucide-react'
import { errorMessage } from '../lib/api-client'
import { Alert } from './ui/alert'
import { Card } from './ui/card'
import { Skeleton } from './ui/skeleton'

export function PageLoading() { return <div className="space-y-4"><Skeleton className="h-28" /><Skeleton className="h-64" /></div> }
export function ErrorState({ error }: { error: unknown }) { return <Alert>{errorMessage(error)}</Alert> }
export function EmptyState({ title, description, action }: { title: string; description: string; action?: React.ReactNode }) {
  return <Card className="flex min-h-56 flex-col items-center justify-center p-8 text-center"><span className="mb-4 rounded-full bg-[#edf1ed] p-3 text-[#53675e]"><Inbox size={22} /></span><h3 className="font-semibold text-[#21342c]">{title}</h3><p className="mt-1 max-w-md text-sm text-[#6a7972]">{description}</p>{action && <div className="mt-5">{action}</div>}</Card>
}
