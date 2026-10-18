import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { FormField } from '@/components/form-field'
import PageLoader from '@/components/page-loader'
import { Button, buttonVariants } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { PasswordInput } from '@/components/ui/password-input'
import { useAuth } from '@/context/AuthContext'
import api, { loadErrorMessage, validationErrors } from '@/lib/api'
import { cn } from '@/lib/utils'

function StatusBadge({ active }) {
  return (
    <span
      className={cn(
        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
        active
          ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
          : 'bg-muted text-muted-foreground',
      )}
    >
      {active ? 'Active' : 'Inactive'}
    </span>
  )
}

/**
 * Self-service password change: verifies the current password server-side
 * (`current_password` rule) and clears any admin-forced reset flag.
 */
function ChangePasswordForm() {
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    setErrors({})
    setSaving(true)

    try {
      await api.put('/profile/password', {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmation,
      })

      toast.success('Password updated successfully.')
      setCurrentPassword('')
      setNewPassword('')
      setConfirmation('')
    } catch (error) {
      const fieldErrors = validationErrors(error)

      if (fieldErrors) {
        setErrors(fieldErrors)
      } else {
        toast.error(loadErrorMessage(error, 'Could not update the password.'))
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <FormField
        id="current_password"
        label="Current Password"
        required
        error={errors.current_password}
      >
        <PasswordInput
          id="current_password"
          autoComplete="current-password"
          value={currentPassword}
          onChange={(event) => setCurrentPassword(event.target.value)}
        />
      </FormField>

      <div className="grid gap-5 sm:grid-cols-2">
        <FormField
          id="password"
          label="New Password"
          required
          error={errors.password}
        >
          <PasswordInput
            id="password"
            autoComplete="new-password"
            value={newPassword}
            onChange={(event) => setNewPassword(event.target.value)}
          />
        </FormField>

        <FormField
          id="password_confirmation"
          label="Confirm New Password"
          required
          error={errors.password_confirmation}
        >
          <PasswordInput
            id="password_confirmation"
            autoComplete="new-password"
            value={confirmation}
            onChange={(event) => setConfirmation(event.target.value)}
          />
        </FormField>
      </div>

      <div className="flex justify-end">
        <Button type="submit" disabled={saving}>
          {saving ? 'Saving…' : 'Update Password'}
        </Button>
      </div>
    </form>
  )
}

/**
 * The signed-in user's own account data, read straight from the auth
 * session (login / refresh payload), plus a self-service password change.
 */
export default function Profile() {
  const { user, loading } = useAuth()

  if (loading || !user) {
    return <PageLoader />
  }

  const memberSince = user.created_at
    ? new Date(user.created_at).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    : null

  const initial = (user.name ?? '?').trim().charAt(0).toUpperCase()

  return (
    <div className="mx-auto max-w-5xl space-y-6">
      <Link to="/" className={buttonVariants({ variant: 'ghost' })}>
        <ArrowLeft aria-hidden="true" /> Back to dashboard
      </Link>

      <Card className="py-8">
        <CardHeader className="flex flex-row flex-wrap items-center gap-4">
          <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary text-lg font-semibold text-primary-foreground">
            {initial}
          </span>
          <div className="space-y-1">
            <div className="flex flex-wrap items-center gap-3">
              <CardTitle>{user.name}</CardTitle>
              <span className="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground capitalize">
                {user.role}
              </span>
            </div>
            <CardDescription>{user.email}</CardDescription>
          </div>
        </CardHeader>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Account Details</CardTitle>
          <CardDescription>
            Your profile data as it is stored on the server.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <dt className="text-muted-foreground">Full Name</dt>
              <dd className="mt-0.5 font-medium">{user.name ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Email</dt>
              <dd className="mt-0.5 font-medium">{user.email ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Login ID</dt>
              <dd className="mt-0.5 font-medium">{user.login_id ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Role</dt>
              <dd className="mt-0.5 font-medium capitalize">
                {user.role ?? '—'}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Gender</dt>
              <dd className="mt-0.5 font-medium capitalize">
                {user.gender ?? '—'}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Status</dt>
              <dd className="mt-0.5 font-medium">
                <StatusBadge active={user.status === 'active'} />
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Member Since</dt>
              <dd className="mt-0.5 font-medium">
                {memberSince ?? '—'}
              </dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Change Password</CardTitle>
          <CardDescription>
            Enter your current password to set a new one.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ChangePasswordForm />
        </CardContent>
      </Card>
    </div>
  )
}
