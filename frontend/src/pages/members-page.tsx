import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { useOutletContext } from "react-router-dom";
import { z } from "zod";
import { FormField } from "../components/form-field";
import { EmptyState, ErrorState, PageLoading } from "../components/page-states";
import { Alert } from "../components/ui/alert";
import { Badge } from "../components/ui/badge";
import { Button } from "../components/ui/button";
import { Card } from "../components/ui/card";
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
import { membersApi } from "../lib/api";
import { errorMessage } from "../lib/api-client";
import type { Role } from "../lib/api-types";
import { shortDate } from "../lib/format";
import type { PortfolioOutletContext } from "./portfolio-layout";

const schema = z.object({
  email: z.string().email(),
  role: z.enum(["owner", "editor", "viewer"]),
});
type Values = z.infer<typeof schema>;

export function MembersPage() {
  const { portfolio } = useOutletContext<PortfolioOutletContext>();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const members = useQuery({
    queryKey: ["portfolio", portfolio.id, "members"],
    queryFn: () => membersApi.list(portfolio.id),
  });
  const items = members.data?.data ?? [];
  const canManage = portfolio.capabilities.manage_members;
  const update = useMutation({
    mutationFn: ({ id, role }: { id: number; role: Role }) =>
      membersApi.update(portfolio.id, id, role),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["portfolio", portfolio.id] }),
  });
  const remove = useMutation({
    mutationFn: (id: number) => membersApi.delete(portfolio.id, id),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ["portfolio", portfolio.id] }),
  });
  if (members.isLoading) return <PageLoading />;
  return (
    <div className="space-y-5">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <h2 className="text-xl font-semibold text-[#192b24]">
            Portfolio members
          </h2>
          <p className="mt-1 text-sm text-[#687871]">
            Owners manage access. Editors can change portfolio data; viewers
            have read-only access.
          </p>
        </div>
        {canManage && (
          <Button onClick={() => setOpen(true)}>
            <Plus size={16} />
            Add member
          </Button>
        )}
      </div>
      {members.error && <ErrorState error={members.error} />}
      {(update.error || remove.error) && (
        <Alert>{errorMessage(update.error ?? remove.error)}</Alert>
      )}
      {!members.error && items.length === 0 ? (
        <EmptyState
          title="No members found"
          description="This portfolio does not have any visible membership records."
        />
      ) : (
        <Card>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Member</TableHead>
                <TableHead>Role</TableHead>
                <TableHead>Added</TableHead>
                {canManage && <TableHead className="w-16">Remove</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((membership) => (
                <TableRow key={membership.id}>
                  <TableCell>
                    <p className="font-medium text-[#25392f]">
                      {membership.user.name}
                    </p>
                    <p className="text-xs text-[#718079]">
                      {membership.user.email}
                    </p>
                  </TableCell>
                  <TableCell>
                    {canManage ? (
                      <select
                        className="field-select max-w-36"
                        value={membership.role}
                        disabled={update.isPending}
                        onChange={(event) =>
                          update.mutate({
                            id: membership.id,
                            role: event.target.value as Role,
                          })
                        }
                      >
                        <option value="owner">Owner</option>
                        <option value="editor">Editor</option>
                        <option value="viewer">Viewer</option>
                      </select>
                    ) : (
                      <Badge>{membership.role}</Badge>
                    )}
                  </TableCell>
                  <TableCell>{shortDate(membership.created_at)}</TableCell>
                  {canManage && (
                    <TableCell>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Remove ${membership.user.name}`}
                        onClick={() => {
                          if (
                            window.confirm(
                              `Remove ${membership.user.name} from this portfolio?`,
                            )
                          )
                            remove.mutate(membership.id);
                        }}
                      >
                        <Trash2 size={16} />
                      </Button>
                    </TableCell>
                  )}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </Card>
      )}
      <AddMemberDialog
        portfolioId={portfolio.id}
        open={open}
        onOpenChange={setOpen}
      />
    </div>
  );
}

function AddMemberDialog({
  portfolioId,
  open,
  onOpenChange,
}: {
  portfolioId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const queryClient = useQueryClient();
  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: "", role: "viewer" },
  });
  const create = useMutation({
    mutationFn: (values: Values) => membersApi.create(portfolioId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["portfolio", portfolioId, "members"],
      });
      reset();
      onOpenChange(false);
    },
    onError: (error) => setError("root", { message: errorMessage(error) }),
  });
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add member</DialogTitle>
          <DialogDescription>
            The user must already have a registered account. Owner access
            includes membership administration.
          </DialogDescription>
        </DialogHeader>
        <form
          className="space-y-4"
          onSubmit={handleSubmit((values) => create.mutate(values))}
        >
          {errors.root?.message && <Alert>{errors.root.message}</Alert>}
          <FormField label="Registered email" error={errors.email?.message}>
            <Input type="email" {...register("email")} />
          </FormField>
          <FormField label="Role" error={errors.role?.message}>
            <select className="field-select" {...register("role")}>
              <option value="viewer">Viewer — read only</option>
              <option value="editor">
                Editor — manage assets and transactions
              </option>
              <option value="owner">
                Owner — full access and membership management
              </option>
            </select>
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
              {create.isPending ? "Adding…" : "Add member"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
