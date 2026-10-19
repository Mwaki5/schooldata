import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/context/AuthContext'
import StudentDashboard from '@/pages/StudentDashboard'

export default function Dashboard() {
  const { user } = useAuth()

  // Students get their own dashboard; every other role keeps the
  // placeholder until its module dashboard is built.
  if (user?.role === 'student') {
    return <StudentDashboard />
  }

  return (
    <div className="mx-auto max-w-5xl">
      <Card>
        <CardHeader>
          <CardTitle>Welcome back, {user?.name}</CardTitle>
          <CardDescription>
            You are signed in as {user?.email}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">
            The dashboard modules (students, teachers, classes, attendance,
            grades, and fees) will be built here step by step.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
