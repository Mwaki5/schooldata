import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FormField } from '@/components/form-field'
import { useAuth } from '@/context/AuthContext'
import { validationErrors } from '@/lib/api'

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const from = location.state?.from?.pathname ?? '/'

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setFormError(null)
    setSubmitting(true)

    const form = new FormData(event.currentTarget)

    try {
      await login({
        email: form.get('email'),
        password: form.get('password'),
      })

      navigate(from, { replace: true })
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        setFormError(
          error.response?.data?.message ?? 'Unable to sign in. Please try again.',
        )
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <main className="flex min-h-svh items-center justify-center bg-muted/40 p-4">
      <div className="w-full max-w-md">
        <Card className="py-8">
          <CardHeader className="text-center">
            <CardTitle className="text-2xl">School Management</CardTitle>
            <CardDescription>
              Sign in to your account to continue
            </CardDescription>
          </CardHeader>

          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-5" noValidate>
              {formError && (
                <div
                  role="alert"
                  className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive"
                >
                  {formError}
                </div>
              )}

              <FormField
                id="email"
                label="Email or Staff No"
                type="text"
                name="email"
                required
                autoComplete="username"
                placeholder="name@example.com or EMP-0001"
                error={errors.email}
              />

              <FormField
                id="password"
                label="Password"
                type="password"
                name="password"
                required
                autoComplete="current-password"
                placeholder="Enter your password"
                error={errors.password}
              />

              <Button
                type="submit"
                size="lg"
                className="w-full rounded-xl"
                disabled={submitting}
              >
                {submitting ? 'Signing in…' : 'Sign In'}
              </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
              Don&apos;t have an account?{' '}
              <Link
                to="/register"
                className="font-medium text-foreground underline underline-offset-4"
              >
                Create one
              </Link>
            </p>
          </CardContent>
        </Card>
      </div>
    </main>
  )
}
