import { zodResolver } from "@hookform/resolvers/zod";
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from "@tanstack/react-query";
import { Archive, Edit3, Plus, ReceiptText } from "lucide-react";
import { useEffect, useState } from "react";
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
import type {
  Asset,
  AssetInput,
  AssetType,
  TransactionInput,
  WritableTransactionKind,
} from "../lib/api-types";
import {
  assetTypeLabel,
  localDateTimeInput,
  money,
  quantity,
  shortDate,
  toLocalDateTimeInput,
  trimDecimalZeros,
} from "../lib/format";
import type { PortfolioOutletContext } from "./portfolio-layout";

const decimal = /^\d+(\.\d{1,8})?$/;
const signedDecimal = /^-?\d+(\.\d{1,8})?$/;
const hasNonZeroDigit = (value: string) => /[1-9]/.test(value);
const assetSchema = z.object({
  type: z.enum(["stock", "crypto", "real_estate", "cash"]),
  name: z.string().trim().min(1).max(160),
  symbol: z.string().trim().max(32),
  current_price: z
    .string()
    .regex(decimal, "Enter a positive decimal with up to 8 places.")
    .refine(hasNonZeroDigit, "Price must be greater than zero."),
  price_as_of: z.string().min(1),
  opening_quantity: z
    .string()
    .refine(
      (value) => value === "" || (decimal.test(value) && hasNonZeroDigit(value)),
      "Enter a positive quantity.",
    ),
});
type AssetValues = z.infer<typeof assetSchema>;

const transactionSchema = z
  .object({
    kind: z.enum(["buy", "sell", "deposit", "withdrawal", "adjustment"]),
    quantity: z
      .string()
      .regex(signedDecimal, "Enter a non-zero decimal with up to 8 places.")
      .refine(hasNonZeroDigit, "Quantity cannot be zero."),
    unit_price: z
      .string()
      .refine(
        (value) => value === "" || (decimal.test(value) && hasNonZeroDigit(value)),
        "Enter a positive price.",
      ),
    fees: z.string().regex(decimal, "Enter a non-negative decimal."),
    occurred_at: z.string().min(1),
    note: z.string().max(500),
  })
  .superRefine((value, context) => {
    if (value.kind !== "adjustment" && value.quantity.startsWith("-"))
      context.addIssue({
        code: "custom",
        path: ["quantity"],
        message: "Only adjustments can be negative.",
      });
    if (value.kind === "adjustment" && !value.note.trim())
      context.addIssue({
        code: "custom",
        path: ["note"],
        message: "Explain the adjustment.",
      });
  });
type TransactionValues = z.infer<typeof transactionSchema>;

export function AssetsPage() {
  const { portfolio } = useOutletContext<PortfolioOutletContext>();
  const queryClient = useQueryClient();
  const [type, setType] = useState("");
  const [includeArchived, setIncludeArchived] = useState(false);
  const [assetDialog, setAssetDialog] = useState<{
    open: boolean;
    asset?: Asset;
  }>({ open: false });
  const [transactionAsset, setTransactionAsset] = useState<Asset>();
  const assets = useInfiniteQuery({
    queryKey: ["portfolio", portfolio.id, "assets", type, includeArchived],
    queryFn: ({ pageParam }) =>
      assetsApi.list(portfolio.id, {
        cursor: pageParam,
        type,
        include_archived: includeArchived ? "true" : undefined,
      }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => last.meta.next_cursor,
  });
  const items = assets.data?.pages.flatMap((page) => page.data) ?? [];
  const archive = useMutation({
    mutationFn: (assetId: number) => assetsApi.archive(portfolio.id, assetId),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["portfolio", portfolio.id, "assets"],
      });
      queryClient.invalidateQueries({
        queryKey: ["portfolio", portfolio.id, "dashboard"],
      });
    },
  });
  const canManage = portfolio.capabilities.manage_assets;
  if (assets.isLoading) return <PageLoading />;
  return (
    <div className="space-y-5">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h2 className="text-xl font-semibold text-[#192b24]">Assets</h2>
          <p className="mt-1 text-sm text-[#687871]">
            Prices are entered manually and clearly dated for this prototype.
          </p>
        </div>
        {canManage && (
          <Button onClick={() => setAssetDialog({ open: true })}>
            <Plus size={16} />
            Add asset
          </Button>
        )}
      </div>
      <Card>
        <CardContent className="grid gap-4 p-4 sm:grid-cols-[minmax(0,13rem)_1fr] sm:items-end">
          <FormField label="Asset type">
            <select
              className="field-select"
              value={type}
              onChange={(event) => setType(event.target.value)}
            >
              <option value="">All asset types</option>
              {Object.entries(assetTypeLabel).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </FormField>
          <label className="flex h-10 items-center gap-2 text-sm text-[#53655d] sm:pb-0">
            <input
              type="checkbox"
              checked={includeArchived}
              onChange={(event) => setIncludeArchived(event.target.checked)}
              className="accent-[#276b58]"
            />
            Include archived assets
          </label>
        </CardContent>
      </Card>
      {assets.error && <ErrorState error={assets.error} />}
      {archive.error && <Alert>{errorMessage(archive.error)}</Alert>}
      {!assets.error && items.length === 0 ? (
        <EmptyState
          title="No assets found"
          description={
            type
              ? "No assets match this filter."
              : "Add the first asset to start building this portfolio."
          }
          action={
            canManage && !type ? (
              <Button onClick={() => setAssetDialog({ open: true })}>
                <Plus size={16} />
                Add asset
              </Button>
            ) : undefined
          }
        />
      ) : (
        <Card>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Asset</TableHead>
                <TableHead>Type</TableHead>
                <TableHead className="text-right">Quantity</TableHead>
                <TableHead className="text-right">Manual price</TableHead>
                <TableHead className="text-right">Value</TableHead>
                {canManage && <TableHead className="w-40">Actions</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((asset) => (
                <TableRow
                  key={asset.id}
                  className={asset.archived_at ? "opacity-60" : ""}
                >
                  <TableCell>
                    <p className="font-medium text-[#20342c]">{asset.name}</p>
                    <p className="text-xs text-[#74817b]">
                      {asset.symbol ?? asset.currency}
                      {asset.archived_at ? " · Archived" : ""}
                    </p>
                  </TableCell>
                  <TableCell>
                    <Badge>{assetTypeLabel[asset.type]}</Badge>
                  </TableCell>
                  <TableCell className="tabular text-right">
                    {quantity(asset.quantity)}
                  </TableCell>
                  <TableCell className="tabular text-right">
                    <p>{money(asset.current_price, asset.currency)}</p>
                    <p className="text-xs text-[#74817b]">
                      {shortDate(asset.price_as_of)}
                    </p>
                  </TableCell>
                  <TableCell className="tabular text-right font-medium">
                    {money(asset.value, portfolio.base_currency)}
                  </TableCell>
                  {canManage && (
                    <TableCell>
                      <div className="flex gap-1">
                        {portfolio.capabilities.record_transactions &&
                          !asset.archived_at && (
                            <Button
                              variant="ghost"
                              size="icon"
                              aria-label={`Record transaction for ${asset.name}`}
                              title="Record transaction"
                              onClick={() => setTransactionAsset(asset)}
                            >
                              <ReceiptText size={16} />
                            </Button>
                          )}
                        {!asset.archived_at && (
                          <>
                            <Button
                              variant="ghost"
                              size="icon"
                              aria-label={`Edit ${asset.name}`}
                              title="Edit asset"
                              onClick={() =>
                                setAssetDialog({ open: true, asset })
                              }
                            >
                              <Edit3 size={16} />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              aria-label={`Archive ${asset.name}`}
                              title="Archive asset"
                              onClick={() => {
                                if (
                                  window.confirm(
                                    `Archive ${asset.name}? Its transaction history will be preserved.`,
                                  )
                                )
                                  archive.mutate(asset.id);
                              }}
                            >
                              <Archive size={16} />
                            </Button>
                          </>
                        )}
                      </div>
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Card>
      )}
      {assets.hasNextPage && (
        <div className="text-center">
          <Button
            variant="secondary"
            onClick={() => assets.fetchNextPage()}
            disabled={assets.isFetchingNextPage}
          >
            {assets.isFetchingNextPage ? "Loading…" : "Load more"}
          </Button>
        </div>
      )}
      <AssetDialog
        portfolioId={portfolio.id}
        currency={portfolio.base_currency}
        state={assetDialog}
        onOpenChange={(open) =>
          setAssetDialog((current) => ({ ...current, open }))
        }
      />
      <TransactionDialog
        portfolioId={portfolio.id}
        asset={transactionAsset}
        onOpenChange={(open) => {
          if (!open) setTransactionAsset(undefined);
        }}
      />
    </div>
  );
}

function AssetDialog({
  portfolioId,
  currency,
  state,
  onOpenChange,
}: {
  portfolioId: number;
  currency: string;
  state: { open: boolean; asset?: Asset };
  onOpenChange: (open: boolean) => void;
}) {
  const queryClient = useQueryClient();
  const asset = state.asset;
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    watch,
    setError,
    formState: { errors },
  } = useForm<AssetValues>({
    resolver: zodResolver(assetSchema),
    defaultValues: {
      type: "stock",
      name: "",
      symbol: "",
      current_price: "1",
      price_as_of: localDateTimeInput(),
      opening_quantity: "",
    },
  });
  useEffect(() => {
    if (state.open)
      reset(
        asset
          ? {
              type: asset.type,
              name: asset.name,
              symbol: asset.symbol ?? "",
              current_price: trimDecimalZeros(asset.current_price),
              price_as_of: toLocalDateTimeInput(asset.price_as_of),
              opening_quantity: "",
            }
          : {
              type: "stock",
              name: "",
              symbol: "",
              current_price: "1",
              price_as_of: localDateTimeInput(),
              opening_quantity: "",
            },
      );
  }, [state.open, asset, reset]);
  const selectedType = watch("type");
  const save = useMutation({
    mutationFn: (values: AssetValues) => {
      const input: AssetInput = {
        type: values.type,
        name: values.name,
        symbol: values.symbol || null,
        currency,
        current_price: trimDecimalZeros(values.current_price),
        price_as_of: new Date(values.price_as_of).toISOString(),
      };
      if (!asset && values.opening_quantity)
        input.opening = {
          quantity: trimDecimalZeros(values.opening_quantity),
          unit_price: trimDecimalZeros(values.current_price),
          occurred_at: new Date(values.price_as_of).toISOString(),
          note: "Opening balance",
        };
      return asset
        ? assetsApi.update(portfolioId, asset.id, input)
        : assetsApi.create(portfolioId, input);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["portfolio", portfolioId, "assets"],
      });
      queryClient.invalidateQueries({
        queryKey: ["portfolio", portfolioId, "dashboard"],
      });
      queryClient.invalidateQueries({
        queryKey: ["portfolio", portfolioId, "transactions"],
      });
      onOpenChange(false);
    },
    onError: (error) => setError("root", { message: errorMessage(error) }),
  });
  return (
    <Dialog open={state.open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{asset ? "Edit asset" : "Add asset"}</DialogTitle>
          <DialogDescription>
            Values use the portfolio currency. No live pricing is inferred.
          </DialogDescription>
        </DialogHeader>
        <form
          onSubmit={handleSubmit((values) => save.mutate(values))}
          className="space-y-4"
        >
          {errors.root?.message && <Alert>{errors.root.message}</Alert>}
          <div className="grid gap-x-4 gap-y-5 sm:grid-cols-2">
            <FormField label="Asset type" error={errors.type?.message}>
              <select className="field-select" {...register("type")}>
                <option value="stock">Stock</option>
                <option value="crypto">Crypto</option>
                <option value="real_estate">Real estate</option>
                <option value="cash">Cash</option>
              </select>
            </FormField>
            <FormField label="Name" error={errors.name?.message}>
              <Input {...register("name")} />
            </FormField>
            <FormField label="Symbol (optional)" error={errors.symbol?.message}>
              <Input className="uppercase" {...register("symbol")} />
            </FormField>
            <FormField
              label={`Current price (${currency})`}
              error={errors.current_price?.message}
              hint={
                selectedType === "cash"
                  ? "Cash must use a price of 1."
                  : "Manual price per unit."
              }
            >
              <Input
                inputMode="decimal"
                {...register("current_price", {
                  onBlur: (event) =>
                    setValue(
                      "current_price",
                      trimDecimalZeros(event.target.value),
                      { shouldValidate: true },
                    ),
                })}
              />
            </FormField>
            <FormField
              label="Price as of"
              error={errors.price_as_of?.message}
              hint="Local date and time for this manual price."
            >
              <Input type="datetime-local" {...register("price_as_of")} />
            </FormField>
            {!asset && (
              <FormField
                label="Opening quantity (optional)"
                error={errors.opening_quantity?.message}
                hint="Creates the initial transaction atomically."
              >
                <Input
                  inputMode="decimal"
                  {...register("opening_quantity", {
                    onBlur: (event) =>
                      setValue(
                        "opening_quantity",
                        trimDecimalZeros(event.target.value),
                        { shouldValidate: true },
                      ),
                  })}
                />
              </FormField>
            )}
          </div>
          <DialogFooter>
            <Button
              type="button"
              variant="secondary"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button disabled={save.isPending}>
              {save.isPending
                ? "Saving…"
                : asset
                  ? "Save changes"
                  : "Add asset"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function TransactionDialog({
  portfolioId,
  asset,
  onOpenChange,
}: {
  portfolioId: number;
  asset?: Asset;
  onOpenChange: (open: boolean) => void;
}) {
  const queryClient = useQueryClient();
  const {
    register,
    handleSubmit,
    reset,
    setValue,
    setError,
    formState: { errors },
  } = useForm<TransactionValues>({
    resolver: zodResolver(transactionSchema),
    defaultValues: {
      kind: "buy",
      quantity: "",
      unit_price: "",
      fees: "0",
      occurred_at: localDateTimeInput(),
      note: "",
    },
  });
  useEffect(() => {
    if (asset)
      reset({
        kind: asset.type === "cash" ? "deposit" : "buy",
        quantity: "",
        unit_price: trimDecimalZeros(asset.current_price),
        fees: "0",
        occurred_at: localDateTimeInput(),
        note: "",
      });
  }, [asset, reset]);
  const create = useMutation({
    mutationFn: (values: TransactionValues) => {
      const input: TransactionInput = {
        kind: values.kind as WritableTransactionKind,
        quantity: trimDecimalZeros(values.quantity),
        unit_price: values.unit_price
          ? trimDecimalZeros(values.unit_price)
          : null,
        fees: trimDecimalZeros(values.fees),
        occurred_at: new Date(values.occurred_at).toISOString(),
        note: values.note || null,
      };
      return transactionsApi.create(portfolioId, asset!.id, input);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["portfolio", portfolioId] });
      onOpenChange(false);
    },
    onError: (error) => setError("root", { message: errorMessage(error) }),
  });
  return (
    <Dialog open={Boolean(asset)} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Record transaction</DialogTitle>
          <DialogDescription>
            {asset
              ? `Add a transaction for ${asset.name}. The backend validates resulting quantity.`
              : ""}
          </DialogDescription>
        </DialogHeader>
        <form
          onSubmit={handleSubmit((values) => create.mutate(values))}
          className="space-y-4"
        >
          {errors.root?.message && <Alert>{errors.root.message}</Alert>}
          <div className="grid gap-x-4 gap-y-5 sm:grid-cols-2">
            <FormField label="Type" error={errors.kind?.message}>
              <select className="field-select" {...register("kind")}>
                <option value="buy">Buy</option>
                <option value="sell">Sell</option>
                <option value="deposit">Deposit</option>
                <option value="withdrawal">Withdrawal</option>
                <option value="adjustment">Adjustment</option>
              </select>
            </FormField>
            <FormField
              label="Quantity"
              error={errors.quantity?.message}
              hint="Use up to 8 decimal places."
            >
              <Input
                inputMode="decimal"
                {...register("quantity", {
                  onBlur: (event) =>
                    setValue(
                      "quantity",
                      trimDecimalZeros(event.target.value),
                      { shouldValidate: true },
                    ),
                })}
              />
            </FormField>
            <FormField
              label="Unit price (optional)"
              error={errors.unit_price?.message}
              hint="Historical price per unit; current valuation still uses the asset's manual price."
            >
              <Input
                inputMode="decimal"
                {...register("unit_price", {
                  onBlur: (event) =>
                    setValue(
                      "unit_price",
                      trimDecimalZeros(event.target.value),
                      { shouldValidate: true },
                    ),
                })}
              />
            </FormField>
            <FormField
              label="Fees"
              error={errors.fees?.message}
              hint="Enter 0 when there are no fees."
            >
              <Input
                inputMode="decimal"
                {...register("fees", {
                  onBlur: (event) =>
                    setValue("fees", trimDecimalZeros(event.target.value), {
                      shouldValidate: true,
                    }),
                })}
              />
            </FormField>
            <FormField
              label="Occurred at"
              error={errors.occurred_at?.message}
              hint="Your local date and time."
            >
              <Input type="datetime-local" {...register("occurred_at")} />
            </FormField>
          </div>
          <FormField
            label="Note"
            error={errors.note?.message}
            hint="Required for adjustments."
          >
            <textarea className="field-textarea" {...register("note")} />
          </FormField>
          <DialogFooter>
            <Button
              type="button"
              variant="secondary"
              onClick={() => onOpenChange(false)}
            >
              Cancel
            </Button>
            <Button disabled={create.isPending}>
              {create.isPending ? "Recording…" : "Record transaction"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
