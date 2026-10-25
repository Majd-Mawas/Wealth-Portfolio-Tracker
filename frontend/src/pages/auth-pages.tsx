import { zodResolver } from "@hookform/resolvers/zod";
import { Landmark } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { z } from "zod";
import { Alert } from "../components/ui/alert";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
import { Input } from "../components/ui/input";
import { FormField } from "../components/form-field";
import { useAuth } from "../features/auth/auth-context";
import { authApi } from "../lib/api";
import { errorMessage } from "../lib/api-client";

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
  remember: z.boolean(),
});
type LoginValues = z.infer<typeof loginSchema>;

function AuthFrame({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle: string;
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[#edf0ec] px-4 py-10">
      <div className="w-full max-w-md">
        <Link
          to="/"
          className="mb-8 flex items-center justify-center gap-2 text-lg font-semibold text-[#183b31]"
        >
          <Landmark size={23} />
          Ledgerly
        </Link>
        <Card>
          <CardContent className="p-7 sm:p-8">
            <h1 className="text-2xl font-semibold tracking-tight text-[#17261f]">
              {title}
            </h1>
            <p className="mt-2 text-sm text-[#67766f]">{subtitle}</p>
            <div className="mt-7">{children}</div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export function LoginPage() {
  const { setUser } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [serverError, setServerError] = useState<string>();
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "", remember: false },
  });
  const submit = handleSubmit(async (values) => {
    setServerError(undefined);
    try {
      const response = await authApi.login(values);
      setUser(response.data);
      const from = (location.state as { from?: string } | null)?.from;
      navigate(from ?? "/portfolios", { replace: true });
    } catch (error) {
      setServerError(errorMessage(error));
    }
  });
  return (
    <AuthFrame
      title="Welcome back"
      subtitle="Sign in to review and manage your portfolios."
    >
      <form className="space-y-5" onSubmit={submit}>
        {serverError && <Alert>{serverError}</Alert>}
        <FormField label="Email" error={errors.email?.message}>
          <Input type="email" autoComplete="email" {...register("email")} />
        </FormField>
        <FormField label="Password" error={errors.password?.message}>
          <Input
            type="password"
            autoComplete="current-password"
            {...register("password")}
          />
        </FormField>
        <label className="flex items-center gap-2 text-sm text-[#506159]">
          <input
            type="checkbox"
            className="accent-[#276b58]"
            {...register("remember")}
          />
          Keep me signed in
        </label>
        <Button className="w-full" disabled={isSubmitting}>
          {isSubmitting ? "Signing in…" : "Sign in"}
        </Button>
        <p className="text-center text-sm text-[#687871]">
          New to Ledgerly?{" "}
          <Link
            className="font-medium text-[#276b58] hover:underline"
            to="/register"
          >
            Create an account
          </Link>
        </p>
      </form>
    </AuthFrame>
  );
}

const registerSchema = z
  .object({
    name: z.string().min(2, "Enter your full name."),
    email: z.string().email(),
    password: z.string().min(8, "Use at least 8 characters."),
    password_confirmation: z.string(),
  })
  .refine((value) => value.password === value.password_confirmation, {
    path: ["password_confirmation"],
    message: "Passwords do not match.",
  });
type RegisterValues = z.infer<typeof registerSchema>;

export function RegisterPage() {
  const { setUser } = useAuth();
  const navigate = useNavigate();
  const [serverError, setServerError] = useState<string>();
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<RegisterValues>({ resolver: zodResolver(registerSchema) });
  const submit = handleSubmit(async (values) => {
    setServerError(undefined);
    try {
      const response = await authApi.register(values);
      setUser(response.data);
      navigate("/portfolios", { replace: true });
    } catch (error) {
      setServerError(errorMessage(error));
    }
  });
  return (
    <AuthFrame
      title="Create your account"
      subtitle="Start with a secure workspace for your wealth data."
    >
      <form className="space-y-5" onSubmit={submit}>
        {serverError && <Alert>{serverError}</Alert>}
        <FormField label="Full name" error={errors.name?.message}>
          <Input autoComplete="name" {...register("name")} />
        </FormField>
        <FormField label="Email" error={errors.email?.message}>
          <Input type="email" autoComplete="email" {...register("email")} />
        </FormField>
        <FormField label="Password" error={errors.password?.message}>
          <Input
            type="password"
            autoComplete="new-password"
            {...register("password")}
          />
        </FormField>
        <FormField
          label="Confirm password"
          error={errors.password_confirmation?.message}
        >
          <Input
            type="password"
            autoComplete="new-password"
            {...register("password_confirmation")}
          />
        </FormField>
        <Button className="w-full" disabled={isSubmitting}>
          {isSubmitting ? "Creating account…" : "Create account"}
        </Button>
        <p className="text-center text-sm text-[#687871]">
          Already have an account?{" "}
          <Link
            className="font-medium text-[#276b58] hover:underline"
            to="/login"
          >
            Sign in
          </Link>
        </p>
      </form>
    </AuthFrame>
  );
}
