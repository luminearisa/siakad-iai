export function formatDate(dateString?: string | null): string {
  if (!dateString) return '-'
  try {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    }).format(date)
  } catch {
    return dateString
  }
}

export function formatDateTime(dateString?: string | null): string {
  if (!dateString) return '-'
  try {
    const date = new Date(dateString)
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date)
  } catch {
    return dateString
  }
}

export function formatTime(timeString?: string | null): string {
  if (!timeString) return '-'
  return timeString.substring(0, 5) // "08:00:00" -> "08:00"
}

export function formatCredits(theory: number, practical: number): string {
  const total = theory + practical
  if (practical > 0) {
    return `${total} SKS (${theory}T/${practical}P)`
  }
  return `${total} SKS`
}
