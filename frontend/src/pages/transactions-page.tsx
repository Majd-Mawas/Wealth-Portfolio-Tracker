import { zodResolver } from "@hookform/resolvers/zod";
import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
} from "@tanstack/react-query";
import { RotateCcw } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { useOutletContext } from "react-router-dom";
import { z } from "zod";
import { FormField } from "../components/form-field";
import { EmptyState, ErrorState, PageLoading } from "../components/page-states";
import { Alert } from "../components/ui/alert";
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "../components/ui/table";
import { assetsApi, transactionsApi } from "../lib/api";
import { errorMessage } from "../lib/api-client";
import type { Asset, Transaction } from "../lib/api-types";
import { dateTime, money, quantity } from "../lib/format";
import type { PortfolioOutletContext } from "./portfolio-layout";

const reasonSchema = z.object({
  reason: z.string().trim().min(3, "Provide a short reason.").max(500),
});
type ReasonValues = z.infer<typeof reasonSchema>;

export function TransactionsPage() {
  const { portfolio } = useOutletContext<PortfolioOutletContext>();
  const [assetId, setAssetId] = useState("");
  const [kind, setKind] = useState("");
  const [occurredFrom, setOccurredFrom] = useState("");
  const [occurredTo, setOccurredTo] = useState("");
  const [reversing, setReversing] = useState<Transaction>();
  const transactions = useInfiniteQuery({
    queryKey: [
      "portfolio",
      portfolio.id,
      "transactions",
      assetId,
      kind,
      occurredFrom,
      occurredTo,
    ],
    queryFn: ({ pageParam }) =>
      transactionsApi.list(portfolio.id, {
        cursor: pageParam,
        asset_id: assetId ? Number(assetId) : undefined,
        kind,
        occurred_from: localDayBoundary(occurredFrom, false),
        occurred_to: localDayBoundary(occurredTo, true),
      }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  });
  const assetOptions = useQuery({
    queryKey: ["portfolio", portfolio.id, "assets", "transaction-filter"],
    queryFn: async () => {
      const allAssets: Asset[] = [];
      let cursor: string | null | undefined;

      do {
        const response = await assetsApi.list(portfolio.id, {
          cursor,
          include_archived: "true",
        });
        allAssets.push(...response.data);
        cursor = response.meta.next_cursor;
      } while (cursor);

      return allAssets;
    },
  });
  const items = transactions.data?.pages.flatMap((page) => page.data) ?? [];
  const canRecord = portfolio.capabilities.record_transactions;
  const hasFilters = Boolean(assetId || kind || occurredFrom || occurredTo);
  if (transactions.isLoading) return <PageLoading />;
  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-xl font-semibold text-[#192b24]">
          Transaction history
        </h2>
        <p className="mt-1 text-sm text-[#687871]">
          An append-only record. Corrections are made with explicit reversals.
        </p>
      </div>
      <Card>
        <CardContent className="grid items-end gap-4 p-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,.8fr)_minmax(0,.8fr)_auto]">
          <FormField label="Asset">
            <select
              className="field-select"
              value={assetId}
              disabled={assetOptions.isLoading}
              onChange={(event) => setAssetId(event.target.value)}
            >
              <option value="">
                {assetOptions.isLoading ? "Loading assets…" : "All assets"}
              </option>
              {assetOptions.data?.map((asset) => (
                <option key={asset.id} value={asset.id}>
                  {asset.name}
                </option>
              ))}
            </select>
          </FormField>
          <FormField label="Transaction type">
            <select
              className="field-select"
              value={kind}
              onChange={(event) => setKind(event.target.value)}
            >
              <option value="">All types</option>
              <option value="buy">Buy</option>
              <option value="sell">Sell</option>
              <option value="deposit">Deposit</option>
              <option value="withdrawal">Withdrawal</option>
              <option value="adjustment">Adjustment</option>
              <option value="reversal">Reversal</option>
            </select>
          </FormField>
          <FormField label="From date">
            <Input
              type="date"
              value={occurredFrom}
              max={occurredTo || undefined}
              onChange={(event) => setOccurredFrom(event.target.value)}
            />
          </FormField>
          <FormField label="To date">
            <Input
              type="date"
              value={occurredTo}
              min={occurredFrom || undefined}
              onChange={(event) => setOccurredTo(event.target.value)}
            />
          </FormField>
          <Button
            type="button"
            variant="secondary"
            disabled={!hasFilters}
            onClick={() => {
              setAssetId("");
              setKind("");
              setOccurredFrom("");
              setOccurredTo("");
            }}
          >
            Clear
          </Button>
        </CardContent>
      </Card>
      {assetOptions.error && <Alert>{errorMessage(assetOptions.error)}</Alert>}
      {transactions.error && <ErrorState error={transactions.error} />}
      {!transactions.error && items.length === 0 ? (
        <EmptyState
          title="No transactions found"
          description="Record a transaction from the Assets page, or change the active filters."
        />
      ) : (
        <Card>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date</TableHead>
                <TableHead>Asset</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-right">Quantity change</TableHead>
                <TableHead className="text-right">Unit price</TableHead>
                <TableHead>Recorded by</TableHead>
                {canRecord && <TableHead className="w-20">Action</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((transaction) => (
                <TableRow key={transaction.id}>
                  <TableCell className="whitespace-nowrap">
                    {dateTime(transaction.occurred_at)}
                  </TableCell>
                  <TableCell>
                    <p className="font-medium text-[#263a32]">
                      {transaction.asset.name}
                    </p>
                    {transaction.note && (
                      <p
                        className="max-w-52 truncate text-xs text-[#74817b]"
                        title={transaction.note}
                      >
                        {transaction.note}
                      </p>
                    )}
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant={
                        transaction.kind === "reversal"
                          ? "red"
                          : transaction.quantity_delta.startsWith("-")
                            ? "neutral"
                            : "green"
                      }
                    >
                      {transaction.kind}
                    </Badge>
                  </TableCell>
                  <TableCell className="tabular text-right">
                    {!transaction.quantity_delta.startsWith("-") ? "+" : ""}
                    {quantity(transaction.quantity_delta)}
                  </TableCell>
                  <TableCell className="tabular text-right">
                    {transaction.unit_price
                      ? money(transaction.unit_price, portfolio.base_currency)
                      : "—"}
                  </TableCell>
                  <TableCell>{transaction.created_by.name}</TableCell>
                  {canRecord && (
                    <TableCell>
                      {transaction.kind !== "reversal" &&
                      !transaction.reversal_of_id && (
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label={`Reverse transaction ${transaction.id}`}
                          title="Reverse transaction"
                          onClick={() => setReversing(transaction)}
                        >
                          <RotateCcw size={15} />
                        </Button>
                      )}
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Card>
      )}
      {transactions.hasNextPage && (
        <div className="text-center">
          <Button
            variant="secondary"
            onClick={() => transactions.fetchNextPage()}
            disabled={transactions.isFetchingNextPage}
          >
            {transactions.isFetchingNextPage ? "Loading…" : "Load more"}
          </Button>
        </div>
      )}
      <ReverseDialog
        portfolioId={portfolio.id}
        transaction={reversing}
        onOpenChange={(open) => {
          if (!open) setReversing(undefined);
        }}
      />
    </div>
  );
}

function localDayBoundary(date: string, endOfDay: boolean) {
  if (!date) return undefined;
  return new Date(
    `${date}T${endOfDay ? "23:59:59.999" : "00:00:00.000"}`,
  ).toISOString();
}

function ReverseDialog({
  portfolioId,
  transaction,
  onOpenChange,
}: {
  portfolioId: number;
  transaction?: Transaction;
  onOpenChange: (open: boolean) => void;
}) {
  const queryClient = useQueryClient();
  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors },
  } = useForm<ReasonValues>({
    resolver: zodResolver(reasonSchema),
    defaultValues: { reason: "" },
  });
  const reverse = useMutation({
    mutationFn: ({ reason }: ReasonValues) =>
      transactionsApi.reverse(portfolioId, transaction!.id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["portfolio", portfolioId] });
      reset();
      onOpenChange(false);
    },
    onError: (error) => setError("root", { message: errorMessage(error) }),
  });
  return (
    <Dialog open={Boolean(transaction)} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Reverse transaction</DialogTitle>
          <DialogDescription>
            This preserves the original entry and creates an equal, opposite
            record. It cannot be undone silently.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit((values) => reverse.mutate(values))}>
          {errors.root?.message && (
            <Alert className="mb-4">{errors.root.message}</Alert>
          )}
          <FormField label="Reason" error={errors.reason?.message}>
            <textarea
              className="field-textarea"
              autoFocus
              {...register("reason")}
            />
          </FormField>
          <DialogFooter>
            <Button
              type="button"
              variant="secondary"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button variant="danger" disabled={reverse.isPending}>
              {reverse.isPending ? "Reversing…" : "Create reversal"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
