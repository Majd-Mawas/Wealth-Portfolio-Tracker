import { cn } from "../../lib/utils";
export function Skeleton({ className }: { className?: string }) {
  return (
    <div className={cn("animate-pulse rounded-md bg-[#e5e9e5]", className)} />
  );
}
