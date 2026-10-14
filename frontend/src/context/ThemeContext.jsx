/* eslint-disable react-refresh/only-export-components */
import { createContext, useCallback, useContext, useEffect, useState } from 'react'

const ThemeContext = createContext(null)

const STORAGE_KEY = 'theme'

/**
 * The stored theme, or `null` when the user has never picked one — `null`
 * means "follow the OS" and is deliberately not persisted.
 */
function getStoredTheme() {
  if (typeof window === 'undefined') {
    return null
  }

  const stored = window.localStorage.getItem(STORAGE_KEY)

  return stored === 'light' || stored === 'dark' ? stored : null
}

function getSystemTheme() {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light'
}

/**
 * Light/dark theme state backed by a `.dark` class on `<html>`.
 *
 * A stored value is an explicit user choice; no stored value means the app
 * tracks the OS preference live. `@custom-variant dark` in `index.css` keys
 * off the class, and `index.html` applies it before React mounts so a reload
 * never flashes the wrong theme.
 */
function ThemeProvider({ children }) {
  const [storedTheme, setStoredTheme] = useState(getStoredTheme)
  const [systemTheme, setSystemTheme] = useState(
    () => typeof window !== 'undefined' && getSystemTheme(),
  )

  // Follow the OS until the user chooses explicitly.
  useEffect(() => {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')

    const handleChange = (event) => setSystemTheme(event.matches ? 'dark' : 'light')

    mediaQuery.addEventListener('change', handleChange)

    return () => mediaQuery.removeEventListener('change', handleChange)
  }, [])

  const theme = storedTheme ?? systemTheme
  const isDark = theme === 'dark'

  useEffect(() => {
    const root = document.documentElement

    root.classList.toggle('dark', isDark)
    root.style.colorScheme = theme
  }, [isDark, theme])

  const setTheme = useCallback((next) => {
    if (next !== 'light' && next !== 'dark') {
      return
    }

    window.localStorage.setItem(STORAGE_KEY, next)
    setStoredTheme(next)
  }, [])

  const toggleTheme = useCallback(() => {
    setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark')
  }, [setTheme])

  return (
    <ThemeContext.Provider value={{ theme, setTheme, toggleTheme }}>
      {children}
    </ThemeContext.Provider>
  )
}

function useTheme() {
  const context = useContext(ThemeContext)

  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider')
  }

  return context
}

export { ThemeProvider, useTheme }
