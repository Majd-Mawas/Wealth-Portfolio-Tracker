import { AlertCircle } from 'lucide-react'
import type * as React from 'react'
import { cn } from '../../lib/utils'

export function Alert({ children, className, variant = 'error' }: React.HTMLAttributes<HTMLDivElement> & { variant?: 'error' | 'info' }) {
  return <div role="alert" className={cn('flex gap-3 rounded-md border px-4 py-3 text-sm', variant === 'error' ? 'border-[#ebc6c2] bg-[#fcf2f1] text-[#843431]' : 'border-[#cbdde7] bg-[#f1f7fa] text-[#315c76]', className)}><AlertCircle className="mt-0.5 shrink-0" size={16} />{children}</div>
}
