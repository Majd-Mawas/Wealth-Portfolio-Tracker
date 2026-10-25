import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  LayoutDashboard,
  ListRestart,
  PackageOpen,
  Settings,
  Users,
} from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { NavLink, Outlet, useNavigate, useParams } from "react-router-dom";
import { z } from "zod";
import { FormField } from "../components/form-field";
import { ErrorState, PageLoading } from "../components/page-states";
import { Alert } from "../components/ui/alert";
import { Badge } from "../components/ui/badge";
import { Button } from "../components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "../components/ui/dialog";
import { Input } from "../components/ui/input";
import { portfoliosApi } from "../lib/api";
import { errorMessage } from "../lib/api-client";
import type { Portfolio } from "../lib/api-types";
import { cn } from "../lib/utils";

export interface PortfolioOutletContext {
  portfolio: Portfolio;
}

export function PortfolioLayout() {
  const portfolioId = Number(useParams().portfolioId);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const portfolio = useQuery({
    queryKey: ["portfolio", portfolioId],
    queryFn: () =>
      portfoliosApi.get(portfolioId).then((response) => response.data),
    enabled: Number.isInteger(portfolioId),
  });
  if (portfolio.isLoading) return <PageLoading />;
  if (portfolio.error || !portfolio.data)
    return <ErrorState error={portfolio.error} />;
  const links = [
    {
      to: `/portfolios/${portfolioId}`,
      label: "Overview",
      icon: LayoutDashboard,
      end: true,
    },
    {
      to: `/portfolios/${portfolioId}/assets`,
      label: "Assets",
      icon: PackageOpen,
    },
    {
      to: `/portfolios/${portfolioId}/transactions`,
      label: "Transactions",
      icon: ListRestart,
    },
    { to: `/portfolios/${portfolioId}/members`, label: "Members", icon: Users },
  ];
  return (
    <div className="space-y-6">
      <div>
        <NavLink
          to="/portfolios"
          className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-[#607169] hover:text-[#276b58]"
        >
          <ArrowLeft size={15} />
          All portfolios
        </NavLink>
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold tracking-tight text-[#17261f] sm:text-3xl">
            {portfolio.data.name}
          </h1>
          <Badge>{portfolio.data.role}</Badge>
          <span className="text-sm text-[#718079]">
            {portfolio.data.base_currency}
          </span>
          {portfolio.data.capabilities.update_portfolio && (
            <Button
              variant="secondary"
              size="sm"
              className="sm:ml-auto"
              onClick={() => setSettingsOpen(true)}
            >
              <Settings size={15} />
              Settings
            </Button>
          )}
        </div>
      </div>
      <nav
        className="-mx-4 flex overflow-x-auto border-y border-[#dce2dd] bg-white px-4 sm:mx-0 sm:rounded-lg sm:border sm:px-2"
        aria-label="Portfolio sections"
      >
        {links.map(({ to, label, icon: Icon, end }) => (
          <NavLink
            key={to}
            to={to}
            end={end}
            className={({ isActive }) =>
              cn(
                "flex shrink-0 items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors",
                isActive
                  ? "border-[#276b58] text-[#1e5949]"
                  : "border-transparent text-[#65766e] hover:text-[#263b32]",
              )
            }
          >
            <Icon size={16} />
            {label}
          </NavLink>
        ))}
      </nav>
      <Outlet
        context={{ portfolio: portfolio.data } satisfies PortfolioOutletContext}
      />
      <PortfolioSettings
        portfolio={portfolio.data}
        open={settingsOpen}
        onOpenChange={setSettingsOpen}
      />
    </div>
  );
}

const settingsSchema = z.object({
  name: z.string().trim().min(2).max(100),
  base_currency: z
    .string()
    .trim()
    .length(3)
    .transform((value) => value.toUpperCase()),
});
type SettingsValues = z.infer<typeof settingsSchema>;

function PortfolioSettings({
  portfolio,
  open,
  onOpenChange,
}: {
  portfolio: Portfolio;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<SettingsValues>({
    resolver: zodResolver(settingsSchema),
    values: { name: portfolio.name, base_currency: portfolio.base_currency },
  });
  const update = useMutation({
    mutationFn: (values: SettingsValues) =>
      portfoliosApi.update(portfolio.id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["portfolio", portfolio.id] });
      queryClient.invalidateQueries({ queryKey: ["portfolios"] });
      onOpenChange(false);
    },
    onError: (error) => setError("root", { message: errorMessage(error) }),
  });
  const remove = useMutation({
    mutationFn: () => portfoliosApi.delete(portfolio.id),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: ["portfolio", portfolio.id] });
      queryClient.invalidateQueries({ queryKey: ["portfolios"] });
      navigate("/portfolios", { replace: true });
    },
  });
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Portfolio settings</DialogTitle>
          <DialogDescription>
            Changes are restricted to owners and rechecked by the API.
          </DialogDescription>
        </DialogHeader>
        <form
          className="space-y-4"
          onSubmit={handleSubmit((values) => update.mutate(values))}
        >
          {errors.root?.message && <Alert>{errors.root.message}</Alert>}
          {remove.error && <Alert>{errorMessage(remove.error)}</Alert>}
          <FormField label="Portfolio name" error={errors.name?.message}>
            <Input {...register("name")} />
          </FormField>
          <FormField
            label="Base currency"
            error={errors.base_currency?.message}
            hint="Changing currency may be rejected once portfolio data exists."
          >
            <Input
              maxLength={3}
              className="uppercase"
              {...register("base_currency")}
            />
          </FormField>
          <div className="mt-6 flex flex-col-reverse justify-between gap-3 border-t border-[#e2e6e2] pt-5 sm:flex-row">
            <Button
              type="button"
              variant="danger"
              disabled={
                !portfolio.capabilities.delete_portfolio || remove.isPending
              }
              onClick={() => {
                if (
                  window.confirm(
                    `Permanently delete ${portfolio.name}? This cannot be undone.`,
                  )
                )
                  remove.mutate();
              }}
            >
              {remove.isPending ? "Deleting…" : "Delete portfolio"}
            </Button>
            <div className="flex justify-end gap-2">
              <Button
                type="button"
                variant="secondary"
                onClick={() => onOpenChange(false)}
              >
                Cancel
              </Button>
              <Button disabled={update.isPending}>
                {update.isPending ? "Saving…" : "Save changes"}
              </Button>
            </div>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
