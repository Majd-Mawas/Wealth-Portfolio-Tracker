import type * as React from "react";
import { Label } from "./ui/label";

export function FormField({
  label,
  error,
  hint,
  children,
}: {
  label: string;
  error?: string;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {children}
      {error ? (
        <p className="text-xs text-[#a03d38]">{error}</p>
      ) : hint ? (
        <p className="text-xs text-[#718079]">{hint}</p>
      ) : null}
    </div>
  );
}
