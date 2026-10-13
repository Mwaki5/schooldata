/**
 * Suspense fallback for lazily-loaded routes. `fullPage` is used while the
 * app shell itself loads; otherwise the fallback renders inside the shell
 * so the sidebar and navbar stay visible during page transitions.
 */
export default function PageLoader({ fullPage = false }) {
  const spinner = (
    <div
      role="status"
      className="size-8 animate-spin rounded-full border-2 border-border border-t-foreground"
    >
      <span className="sr-only">Loading…</span>
    </div>
  )

  if (fullPage) {
    return (
      <main className="flex min-h-svh items-center justify-center bg-muted/40">
        {spinner}
      </main>
    )
  }

  return (
    <div className="flex min-h-[50vh] items-center justify-center">
      {spinner}
    </div>
  )
}
