import * as React from 'react'
import { cn } from '../../lib/utils'

export const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(({ className, ...props }, ref) => (
  <input ref={ref} className={cn('flex h-10 w-full rounded-md border border-[#cdd5cf] bg-white px-3 py-2 text-sm text-[#17261f] shadow-sm outline-none placeholder:text-[#87928d] focus:border-[#397563] focus:ring-2 focus:ring-[#397563]/15 disabled:cursor-not-allowed disabled:bg-[#f0f2ef]', className)} {...props} />
))
Input.displayName = 'Input'
