import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FormField } from '@/components/form-field'
import { PasswordInput } from '@/components/ui/password-input'
import { useAuth } from '@/context/AuthContext'
import { validationErrors } from '@/lib/api'

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()

  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState(null)
  const [submitting, setSubmitting] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()

    setErrors({})
    setFormError(null)
    setSubmitting(true)

    const form = new FormData(event.currentTarget)

    try {
      await register({
        name: form.get('name'),
        email: form.get('email'),
        password: form.get('password'),
        password_confirmation: form.get('password_confirmation'),
      })

      navigate('/', { replace: true })
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (Object.keys(fieldErrors).length > 0) {
        setErrors(fieldErrors)
      } else {
        setFormError(
          error.response?.data?.message ??
            'Unable to create your account. Please try again.',
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
            <CardTitle className="text-2xl">Create Account</CardTitle>
            <CardDescription>
              Register a new account for the School Management System
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
                id="name"
                label="Full Name"
                type="text"
                name="name"
                required
                autoComplete="name"
                placeholder="Name of User"
                error={errors.name}
              />

              <FormField
                id="email"
                label="Email Address"
                type="email"
                name="email"
                required
                autoComplete="email"
                placeholder="name@example.com"
                error={errors.email}
              />

              <FormField
                id="password"
                label="Password"
                required
                error={errors.password}
              >
                <PasswordInput
                  id="password"
                  name="password"
                  required
                  autoComplete="new-password"
                  placeholder="Minimum 8 characters"
                />
              </FormField>

              <FormField
                id="password_confirmation"
                label="Confirm Password"
                required
                error={errors.password_confirmation}
              >
                <PasswordInput
                  id="password_confirmation"
                  name="password_confirmation"
                  required
                  autoComplete="new-password"
                  placeholder="Re-enter your password"
                />
              </FormField>

              <Button
                type="submit"
                size="lg"
                className="w-full rounded-xl"
                disabled={submitting}
              >
                {submitting ? 'Creating account…' : 'Create Account'}
              </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
              Already have an account?{' '}
              <Link
                to="/login"
                className="font-medium text-foreground underline underline-offset-4"
              >
                Sign in
              </Link>
            </p>
          </CardContent>
        </Card>
      </div>
    </main>
  )
}
