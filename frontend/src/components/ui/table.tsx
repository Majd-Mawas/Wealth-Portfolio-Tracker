import type * as React from 'react'
import { cn } from '../../lib/utils'

export function Table({ className, ...props }: React.TableHTMLAttributes<HTMLTableElement>) { return <div className="w-full overflow-x-auto"><table className={cn('w-full min-w-[680px] text-sm', className)} {...props} /></div> }
export function TableHeader(props: React.HTMLAttributes<HTMLTableSectionElement>) { return <thead className="border-b border-[#dfe4df] bg-[#f8f9f7]" {...props} /> }
export function TableBody(props: React.HTMLAttributes<HTMLTableSectionElement>) { return <tbody className="divide-y divide-[#e7ebe7]" {...props} /> }
export function TableRow({ className, ...props }: React.HTMLAttributes<HTMLTableRowElement>) { return <tr className={cn('hover:bg-[#fafbf9]', className)} {...props} /> }
export function TableHead({ className, ...props }: React.ThHTMLAttributes<HTMLTableCellElement>) { return <th className={cn('h-11 px-4 text-left text-xs font-semibold uppercase tracking-[0.08em] text-[#6a7872]', className)} {...props} /> }
export function TableCell({ className, ...props }: React.TdHTMLAttributes<HTMLTableCellElement>) { return <td className={cn('px-4 py-3.5 text-[#34463f]', className)} {...props} /> }
