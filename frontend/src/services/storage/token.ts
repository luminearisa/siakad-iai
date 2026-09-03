const TOKEN_KEY = 'siakad_auth_token'

export const tokenStorage = {
  get(): string | null {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        return window.localStorage.getItem(TOKEN_KEY)
      }
    } catch {
      // Ignore storage errors in restricted contexts
    }
    return null
  },

  set(token: string): void {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.setItem(TOKEN_KEY, token)
      }
    } catch {
      // Ignore storage errors in restricted contexts
    }
  },

  remove(): void {
    try {
      if (typeof window !== 'undefined' && window.localStorage) {
        window.localStorage.removeItem(TOKEN_KEY)
      }
    } catch {
      // Ignore storage errors in restricted contexts
    }
  },

  has(): boolean {
    return !!this.get()
  },
}
