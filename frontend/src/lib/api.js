import axios from 'axios'
import { toast } from 'sonner'

/**
 * Security model (JWT access + refresh tokens):
 *
 * - Access token (15 min): kept in memory only — never persisted, so it
 *   disappears on reload and XSS cannot outlive the tab.
 * - Refresh token (7 days): lives in an httpOnly cookie the browser attaches
 *   automatically; JavaScript can never read it. The server rotates it on
 *   every use and blacklists the previous one.
 *
 * The API is same-origin through the Vite dev proxy (`/api` -> `:8000`),
 * which is what lets the cookie work without CORS cookie juggling.
 */
const api = axios.create({
  baseURL: '/api',
  headers: {
    Accept: 'application/json',
  },
})

let accessToken = null

export function setAccessToken(token) {
  accessToken = token
}

let refreshPromise = null

/**
 * Exchange the httpOnly refresh cookie for a fresh access token. Single-flight:
 * concurrent 401s share one refresh request.
 */
export function refreshSession() {
  if (!refreshPromise) {
    refreshPromise = axios
      .post('/api/refresh')
      .then(({ data }) => {
        setAccessToken(data.access_token)

        return data
      })
      .finally(() => {
        refreshPromise = null
      })
  }

  return refreshPromise
}

api.interceptors.request.use((config) => {
  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    // Rate limited by the backend's throttle middleware: tell the user
    // instead of letting the request fail silently.
    if (error.response?.status === 429) {
      toast.error(
        'Too many requests. Please wait a minute before trying again.',
      )
    }

    const originalRequest = error.config
    const isUnauthorized = error.response?.status === 401
    const isAuthRoute = ['/login', '/refresh', '/logout'].includes(
      originalRequest?.url,
    )

    if (!isUnauthorized || !originalRequest || isAuthRoute) {
      return Promise.reject(error)
    }

    if (originalRequest._retry) {
      // Refreshed once already and still rejected — give up on this request.
      return Promise.reject(error)
    }

    originalRequest._retry = true

    try {
      const data = await refreshSession()

      originalRequest.headers.Authorization = `Bearer ${data.access_token}`

      return api(originalRequest)
    } catch (refreshError) {
      setAccessToken(null)
      window.location.assign('/login')

      return Promise.reject(refreshError)
    }
  },
)

/**
 * Extract Laravel validation errors (error.response.data.errors) into a
 * flat `{ field: message }` object.
 *
 * @param {unknown} error
 * @returns {Record<string, string>}
 */
export function validationErrors(error) {
  const errors = axios.isAxiosError(error)
    ? error.response?.data?.errors
    : undefined

  if (!errors || typeof errors !== 'object') {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  )
}

/**
 * Whether a failed request was answered with an HTTP 404: the API has no
 * such record (route-model binding) or no such endpoint at all.
 *
 * @param {unknown} error
 * @returns {boolean}
 */
export function isNotFound(error) {
  return axios.isAxiosError(error) && error.response?.status === 404
}

/**
 * Message for a record that failed to load: a specific "not found" note when
 * the API answered 404, the generic fallback for every other failure
 * (validation, 500, network down, ...).
 *
 * @param {unknown} error
 * @param {string} label Resource label, e.g. `'Student'`.
 * @param {string} fallback Message shown when the failure is not a 404.
 * @returns {string}
 */
export function loadErrorMessage(error, label, fallback) {
  return isNotFound(error) ? `${label} not found. It may have been deleted.` : fallback
}

export default api
