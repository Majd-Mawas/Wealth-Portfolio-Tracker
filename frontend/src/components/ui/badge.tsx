import type * as React from 'react'
import { cn } from '../../lib/utils'

export function Badge({ className, variant = 'neutral', ...props }: React.HTMLAttributes<HTMLSpanElement> & { variant?: 'neutral' | 'green' | 'red' | 'blue' }) {
  const variants = { neutral: 'bg-[#edf0ed] text-[#506159]', green: 'bg-[#e2f0e9] text-[#24634f]', red: 'bg-[#f8e5e3] text-[#8f3633]', blue: 'bg-[#e5eef4] text-[#315c76]' }
  return <span className={cn('inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize', variants[variant], className)} {...props} />
}
