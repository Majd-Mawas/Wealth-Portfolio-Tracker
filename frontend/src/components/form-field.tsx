import {
  cloneElement,
  useId,
  type ReactElement,
} from "react";
import { Label } from "./ui/label";

interface FieldControlProps {
  id?: string;
  "aria-describedby"?: string;
  "aria-invalid"?: boolean;
}

export function FormField({
  label,
  error,
  hint,
  children,
}: {
  label: string;
  error?: string;
  hint?: string;
  children: ReactElement<FieldControlProps>;
}) {
  const generatedId = useId();
  const controlId = children.props.id ?? generatedId;
  const hintId = hint ? `${controlId}-hint` : undefined;
  const errorId = error ? `${controlId}-error` : undefined;
  const describedBy = [
    children.props["aria-describedby"],
    hintId,
    errorId,
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <div className="space-y-1.5">
      <Label htmlFor={controlId}>{label}</Label>
      {cloneElement(children, {
        id: controlId,
        "aria-describedby": describedBy || undefined,
        "aria-invalid": error ? true : children.props["aria-invalid"],
      })}
      {hint && (
        <p id={hintId} className="text-xs leading-5 text-[#718079]">
          {hint}
        </p>
      )}
      {error && (
        <p
          id={errorId}
          className="text-xs leading-5 text-[#a03d38]"
          aria-live="polite"
        >
          {error}
        </p>
      )}
    </div>
  );
}
