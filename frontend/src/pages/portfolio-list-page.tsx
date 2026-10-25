import { zodResolver } from "@hookform/resolvers/zod";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { ArrowRight, BriefcaseBusiness, Plus } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { Link } from "react-router-dom";
import { z } from "zod";
import { EmptyState, ErrorState, PageLoading } from "../components/page-states";
import { FormField } from "../components/form-field";
import { Badge } from "../components/ui/badge";
import { Button } from "../components/ui/button";
import { Card, CardContent } from "../components/ui/card";
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

const schema = z.object({
  name: z.string().trim().min(2).max(100),
  base_currency: z
    .string()
    .trim()
    .length(3)
    .transform((value) => value.toUpperCase()),
});
type Values = z.infer<typeof schema>;

export function PortfolioListPage() {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const portfolios = useInfiniteQuery({
    queryKey: ["portfolios"],
    queryFn: ({ pageParam }) => portfoliosApi.list(pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  });
  const items = portfolios.data?.pages.flatMap((page) => page.data) ?? [];
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", base_currency: "USD" },
  });
  const create = useMutation({
    mutationFn: portfoliosApi.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["portfolios"] });
      setOpen(false);
      reset();
    },
    onError: (error) => setError("root", { message: errorMessage(error) }),
  });

  const createButton = (
    <Button onClick={() => setOpen(true)}>
      <Plus size={16} />
      New portfolio
    </Button>
  );
  if (portfolios.isLoading) return <PageLoading />;

  return (
    <div className="space-y-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.12em] text-[#557067]">
            Wealth overview
          </p>
          <h1 className="mt-1 text-3xl font-semibold tracking-tight text-[#17261f]">
            Your portfolios
          </h1>
          <p className="mt-2 text-sm text-[#687871]">
            Review the accounts you own or collaborate on.
          </p>
        </div>
        {createButton}
      </div>
      {portfolios.error && <ErrorState error={portfolios.error} />}
      {!portfolios.error && items.length === 0 ? (
        <EmptyState
          title="No portfolios yet"
          description="Create a portfolio to begin tracking assets and transaction history."
          action={createButton}
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {items.map((portfolio) => (
            <Link
              key={portfolio.id}
              to={`/portfolios/${portfolio.id}`}
              className="group"
            >
              <Card className="h-full transition-colors hover:border-[#92aa9e]">
                <CardContent className="p-5">
                  <div className="flex items-start justify-between gap-4">
                    <span className="rounded-lg bg-[#e8eeea] p-2.5 text-[#276b58]">
                      <BriefcaseBusiness size={20} />
                    </span>
                    <Badge>{portfolio.role}</Badge>
                  </div>
                  <h2 className="mt-5 text-lg font-semibold text-[#1d3028]">
                    {portfolio.name}
                  </h2>
                  <p className="mt-1 text-sm text-[#697871]">
                    Reporting currency: {portfolio.base_currency}
                  </p>
                  <div className="mt-6 flex items-center justify-between border-t border-[#e6eae6] pt-4 text-sm font-medium text-[#276b58]">
                    <span>Open portfolio</span>
                    <ArrowRight
                      size={16}
                      className="transition-transform group-hover:translate-x-1"
                    />
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      )}
      {portfolios.hasNextPage && (
        <div className="text-center">
          <Button
            variant="secondary"
            onClick={() => portfolios.fetchNextPage()}
            disabled={portfolios.isFetchingNextPage}
          >
            {portfolios.isFetchingNextPage ? "Loading…" : "Load more"}
          </Button>
        </div>
      )}
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Create portfolio</DialogTitle>
            <DialogDescription>
              Set the reporting currency once; asset values remain in their
              documented prototype currencies.
            </DialogDescription>
          </DialogHeader>
          <form
            onSubmit={handleSubmit((values) => create.mutate(values))}
            className="space-y-4"
          >
            {errors.root?.message && (
              <p className="text-sm text-[#a03d38]">{errors.root.message}</p>
            )}
            <FormField label="Portfolio name" error={errors.name?.message}>
              <Input autoFocus {...register("name")} />
            </FormField>
            <FormField
              label="Base currency"
              error={errors.base_currency?.message}
              hint="Three-letter ISO currency code, such as USD."
            >
              <Input
                maxLength={3}
                className="uppercase"
                {...register("base_currency")}
              />
            </FormField>
            <DialogFooter>
              <Button
                type="button"
                variant="secondary"
                onClick={() => setOpen(false)}
              >
                Cancel
              </Button>
              <Button disabled={create.isPending}>
                {create.isPending ? "Creating…" : "Create portfolio"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
