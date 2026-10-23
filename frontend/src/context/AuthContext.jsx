/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, useEffect, useState } from 'react'
import api, { refreshSession, setAccessToken } from '@/lib/api'

const AuthContext = createContext(null)

/**
 * Provides authentication state and JWT auth actions backed by the Laravel
 * API (`/api/register`, `/api/login`, `/api/refresh`, `/api/logout`,
 * `/api/user`).
 *
 * The access token is held in memory only; the refresh token lives in an
 * httpOnly cookie, so a page reload silently re-authenticates by exchanging
 * the cookie for a new access token.
 */
function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true

    // Silent sign-in: exchange the httpOnly refresh cookie for a session.
    refreshSession()
      .then((data) => {
        if (active) {
          setUser(data.user)
        }
      })
      .catch(() => {
        // No (valid) refresh cookie — the visitor simply starts signed out.
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const login = async (credentials) => {
    const { data } = await api.post('/login', credentials)

    setAccessToken(data.access_token)
    setUser(data.user)

    return data.user
  }

  const register = async (payload) => {
    const { data } = await api.post('/register', payload)

    setAccessToken(data.access_token)
    setUser(data.user)

    return data.user
  }

  const logout = async () => {
    try {
      // Public endpoint: revokes both tokens server-side and clears the
      // refresh cookie, so the silent sign-in cannot resurrect the session.
      await api.post('/logout')
    } finally {
      setAccessToken(null)
      setUser(null)
    }
  }

  const can = (permission) =>
    Boolean(user?.permissions?.includes(permission))

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, can }}>
      {children}
    </AuthContext.Provider>
  )
}

function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider')
  }

  return context
}

export { AuthProvider, useAuth }
