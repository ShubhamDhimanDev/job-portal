import { Briefcase, FileText, Star, UserPlus, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';

interface DashboardProps {
    stats: {
        publishedJobs: number;
        draftJobs: number;
        totalCandidates: number;
        newCandidatesThisWeek: number;
        shortlistedCandidates: number;
    };
}

export default function Dashboard({ stats }: DashboardProps) {
    const cards = [
        {
            label: 'Published Jobs',
            value: stats.publishedJobs,
            icon: Briefcase,
        },
        {
            label: 'Draft Jobs',
            value: stats.draftJobs,
            icon: FileText,
        },
        {
            label: 'Total Candidates',
            value: stats.totalCandidates,
            icon: Users,
        },
        {
            label: 'New This Week',
            value: stats.newCandidatesThisWeek,
            icon: UserPlus,
        },
        {
            label: 'Shortlisted',
            value: stats.shortlistedCandidates,
            icon: Star,
        },
    ];

    return (
        <AdminLayout title="Dashboard">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                {cards.map((card) => (
                    <Card key={card.label}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">
                                {card.label}
                            </CardTitle>
                            <card.icon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {card.value}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AdminLayout>
    );
}
