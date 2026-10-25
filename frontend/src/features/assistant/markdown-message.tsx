import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { cn } from '../../lib/utils'

interface MarkdownMessageProps {
  content: string
  className?: string
}

export function MarkdownMessage({ content, className }: MarkdownMessageProps) {
  return (
    <div className={cn('markdown-message', className)}>
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        skipHtml
        urlTransform={safeUrlTransform}
        components={{
          a: ({ children, href }) => (
            <a
              href={href}
              rel="noopener noreferrer"
              target={href?.startsWith('http://') || href?.startsWith('https://') ? '_blank' : undefined}
            >
              {children}
            </a>
          ),
          table: ({ children }) => (
            <div className="markdown-table-wrapper">
              <table>{children}</table>
            </div>
          ),
        }}
      >
        {content}
      </ReactMarkdown>
    </div>
  )
}

function safeUrlTransform(url: string): string {
  const normalizedUrl = url.trim().toLowerCase()

  if (
    normalizedUrl.startsWith('https://')
    || normalizedUrl.startsWith('http://')
    || normalizedUrl.startsWith('mailto:')
    || (normalizedUrl.startsWith('/') && !normalizedUrl.startsWith('//'))
    || normalizedUrl.startsWith('#')
  ) {
    return url
  }

  return ''
}
