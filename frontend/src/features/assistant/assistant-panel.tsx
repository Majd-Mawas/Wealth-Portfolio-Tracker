import { Bot, Database, Eraser, Send, ShieldCheck, Square } from "lucide-react";
import { useRef, useState } from "react";
import type { FormEvent } from "react";
import { assistantApi } from "../../lib/api";
import { errorMessage } from "../../lib/api-client";
import type {
  AssistantMessage,
  AssistantStreamEvent,
} from "../../lib/api-types";
import { cn } from "../../lib/utils";
import { Alert } from "../../components/ui/alert";
import { Button } from "../../components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "../../components/ui/card";
import { MarkdownMessage } from "./markdown-message";

interface ChatEntry extends AssistantMessage {
  id: string;
  local?: boolean;
  error?: boolean;
}

interface ToolNotice {
  id: string;
  tool: string;
  status: "running" | "complete" | "error";
  message?: string;
}

const welcome: ChatEntry = {
  id: "welcome",
  role: "assistant",
  local: true,
  content:
    "Ask about this portfolio’s value, allocation, assets, or transaction history. I can read data, but I cannot change it.",
};

const suggestions = [
  "How is this portfolio allocated?",
  "Which assets have the largest values?",
  "Summarize the most recent transactions.",
];

const toolLabels: Record<string, string> = {
  get_portfolio_summary: "Portfolio summary",
  list_portfolio_assets: "Portfolio assets",
  list_portfolio_transactions: "Transaction history",
};

export function AssistantPanel({ portfolioId }: { portfolioId: number }) {
  const [entries, setEntries] = useState<ChatEntry[]>([welcome]);
  const [draft, setDraft] = useState("");
  const [toolNotices, setToolNotices] = useState<ToolNotice[]>([]);
  const [requestId, setRequestId] = useState<string>();
  const [busy, setBusy] = useState(false);
  const [clientError, setClientError] = useState<string>();
  const controller = useRef<AbortController | undefined>(undefined);

  const submit = async (event?: FormEvent) => {
    event?.preventDefault();
    const content = draft.trim();
    if (!content || busy) return;
    if (content.length > 4000) {
      setClientError("Messages may not exceed 4,000 characters.");
      return;
    }

    const userEntry: ChatEntry = {
      id: crypto.randomUUID(),
      role: "user",
      content,
    };
    const assistantId = crypto.randomUUID();
    const assistantEntry: ChatEntry = {
      id: assistantId,
      role: "assistant",
      content: "",
    };
    const history = [
      ...entries
        .filter((entry) => !entry.local)
        .map(({ role, content: text }) => ({ role, content: text })),
      { role: "user" as const, content },
    ].slice(-12);

    setEntries((current) => [...current, userEntry, assistantEntry]);
    setDraft("");
    setToolNotices([]);
    setRequestId(undefined);
    setClientError(undefined);
    setBusy(true);
    controller.current = new AbortController();

    const handleEvent = (streamEvent: AssistantStreamEvent) => {
      if (streamEvent.event === "meta")
        setRequestId(streamEvent.data.request_id);
      if (streamEvent.event === "text_delta") {
        setEntries((current) =>
          current.map((entry) =>
            entry.id === assistantId
              ? { ...entry, content: entry.content + streamEvent.data.text }
              : entry,
          ),
        );
      }
      if (streamEvent.event === "tool_started") {
        setToolNotices((current) => [
          ...current,
          {
            id: crypto.randomUUID(),
            tool: streamEvent.data.tool,
            status: "running",
          },
        ]);
      }
      if (streamEvent.event === "tool_completed") {
        setToolNotices((current) =>
          updateLatestTool(current, streamEvent.data.tool, "complete"),
        );
      }
      if (streamEvent.event === "tool_error") {
        setToolNotices((current) =>
          updateLatestTool(
            current,
            streamEvent.data.tool,
            "error",
            streamEvent.data.message,
          ),
        );
      }
      if (streamEvent.event === "error") {
        setEntries((current) =>
          current.map((entry) =>
            entry.id === assistantId
              ? {
                  ...entry,
                  error: true,
                  content: entry.content || streamEvent.data.message,
                }
              : entry,
          ),
        );
      }
    };

    try {
      await assistantApi.chat(
        portfolioId,
        history,
        handleEvent,
        controller.current.signal,
      );
    } catch (error) {
      const message =
        error instanceof DOMException && error.name === "AbortError"
          ? "Response stopped."
          : errorMessage(error);
      setEntries((current) =>
        current.map((entry) =>
          entry.id === assistantId
            ? { ...entry, error: true, content: entry.content || message }
            : entry,
        ),
      );
    } finally {
      setBusy(false);
      controller.current = undefined;
    }
  };

  return (
    <Card className="overflow-hidden">
      <CardHeader className="border-b border-[#e2e7e3] bg-[#f8faf8]">
        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
          <div>
            <CardTitle className="flex items-center gap-2">
              <span className="rounded-md bg-[#dfeae4] p-1.5 text-[#276b58]">
                <Bot size={17} />
              </span>
              Portfolio assistant{" "}
              <span className="rounded-full border border-[#cdd9d2] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-[#587067]">
                Read only
              </span>
            </CardTitle>
            <p className="mt-2 max-w-2xl text-sm text-[#687871]">
              Answers use this portfolio’s manual prices and ledger. Selected
              portfolio data is sent securely to OpenAI for this response.
            </p>
          </div>
          <Button
            variant="ghost"
            size="sm"
            disabled={busy || entries.length === 1}
            onClick={() => {
              setEntries([welcome]);
              setToolNotices([]);
              setRequestId(undefined);
              setClientError(undefined);
            }}
          >
            <Eraser size={15} />
            Clear
          </Button>
        </div>
      </CardHeader>
      <CardContent className="p-0">
        <div
          className="max-h-[32rem] min-h-72 space-y-5 overflow-y-auto p-4 sm:p-6"
          aria-live="polite"
        >
          {entries.map((entry) => (
            <div
              key={entry.id}
              className={cn(
                "flex",
                entry.role === "user" ? "justify-end" : "justify-start",
              )}
            >
              <div
                className={cn(
                  "max-w-[88%] rounded-xl px-4 py-3 text-sm leading-6 sm:max-w-[78%]",
                  entry.role === "user"
                    ? "bg-[#183b31] text-white"
                    : entry.error
                      ? "border border-[#ebc6c2] bg-[#fcf2f1] text-[#843431]"
                      : "border border-[#dfe5e0] bg-white text-[#34463f]",
                )}
              >
                {entry.role === "assistant" ? (
                  <MarkdownMessage
                    content={
                      entry.content || (busy ? "Reviewing the portfolio…" : "")
                    }
                  />
                ) : (
                  <p className="whitespace-pre-wrap">{entry.content}</p>
                )}
              </div>
            </div>
          ))}
          {toolNotices.length > 0 && (
            <div className="space-y-2 border-l-2 border-[#dce5df] pl-3">
              {toolNotices.map((notice) => (
                <div
                  key={notice.id}
                  className={cn(
                    "flex items-start gap-2 text-xs",
                    notice.status === "error"
                      ? "text-[#963d38]"
                      : "text-[#65766e]",
                  )}
                >
                  <Database size={13} className="mt-0.5 shrink-0" />
                  <span>
                    <strong className="font-medium">
                      {toolLabels[notice.tool] ?? notice.tool}
                    </strong>{" "}
                    —{" "}
                    {notice.status === "running"
                      ? "reading…"
                      : notice.status === "complete"
                        ? "complete"
                        : notice.message}
                  </span>
                </div>
              ))}
            </div>
          )}
          {!busy && entries.length === 1 && (
            <div className="flex flex-wrap gap-2">
              {suggestions.map((suggestion) => (
                <button
                  key={suggestion}
                  type="button"
                  className="rounded-full border border-[#d5ddd7] bg-white px-3 py-2 text-left text-xs text-[#4e6259] transition-colors hover:border-[#88a397] hover:text-[#205c4b]"
                  onClick={() => setDraft(suggestion)}
                >
                  {suggestion}
                </button>
              ))}
            </div>
          )}
        </div>
        <div className="border-t border-[#e2e7e3] bg-[#fbfcfb] p-4 sm:p-5">
          {clientError && <Alert className="mb-3">{clientError}</Alert>}
          <form
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
            onSubmit={submit}
          >
            <label className="flex-1">
              <span className="sr-only">Message the portfolio assistant</span>
              <textarea
                className="field-textarea min-h-20 bg-white"
                value={draft}
                maxLength={4000}
                disabled={busy}
                placeholder="Ask a question about this portfolio…"
                onChange={(event) => setDraft(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === "Enter" && !event.shiftKey) {
                    event.preventDefault();
                    void submit();
                  }
                }}
              />
            </label>
            {busy ? (
              <Button
                type="button"
                variant="secondary"
                onClick={() => controller.current?.abort()}
              >
                <Square size={14} />
                Stop
              </Button>
            ) : (
              <Button type="submit" disabled={!draft.trim()}>
                <Send size={15} />
                Send
              </Button>
            )}
          </form>
          <div className="mt-3 flex flex-col justify-between gap-2 text-xs text-[#718079] sm:flex-row sm:items-center">
            <span className="flex items-center gap-1.5">
              <ShieldCheck size={13} />
              Authorization is rechecked for every tool call.
            </span>
            {requestId && (
              <span className="font-mono">Request {requestId.slice(-8)}</span>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function updateLatestTool(
  notices: ToolNotice[],
  tool: string,
  status: ToolNotice["status"],
  message?: string,
) {
  let index = -1;
  for (
    let noticeIndex = notices.length - 1;
    noticeIndex >= 0;
    noticeIndex -= 1
  ) {
    const notice = notices[noticeIndex];
    if (notice?.tool === tool && notice.status === "running") {
      index = noticeIndex;
      break;
    }
  }
  if (index === -1)
    return [...notices, { id: crypto.randomUUID(), tool, status, message }];
  return notices.map((notice, noticeIndex) =>
    noticeIndex === index ? { ...notice, status, message } : notice,
  );
}
