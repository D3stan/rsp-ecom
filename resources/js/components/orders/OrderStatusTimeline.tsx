import { Timeline, TimelineBody, TimelineConnector, TimelineHeader, TimelineIcon, TimelineItem, TimelineTitle } from '@/components/ui/timeline';
import useTranslation from '@/hooks/useTranslation';
import { CheckCircle, Circle, Package, XCircle } from 'lucide-react';

interface OrderStatusTimelineProps {
    status: string;
}

export default function OrderStatusTimeline({ status }: OrderStatusTimelineProps) {
    const { t } = useTranslation();
    const normalizedStatus = (status ?? '').toLowerCase();

    if (normalizedStatus === 'cancelled') {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <XCircle className="h-4 w-4 shrink-0 text-red-600" />
                <span className="font-medium">{t('orders.cancelled_state')}</span>
            </div>
        );
    }

    const statuses = [
        { key: 'pending', label: t('orders.timeline.order_placed') },
        { key: 'processing', label: t('orders.timeline.processing') },
        { key: 'shipped', label: t('orders.timeline.shipped') },
        { key: 'delivered', label: t('orders.timeline.delivered') },
    ];

    const currentStatusIndex = statuses.findIndex((s) => s.key === normalizedStatus);

    const getStatusIcon = (stepIndex: number) => {
        if (currentStatusIndex !== -1 && stepIndex < currentStatusIndex) {
            return <CheckCircle className="h-4 w-4 text-green-500" />;
        }
        if (currentStatusIndex !== -1 && stepIndex === currentStatusIndex) {
            return <Package className="h-4 w-4 text-blue-500" />;
        }
        return <Circle className="h-4 w-4 text-gray-400" />;
    };

    const getStatusColor = (stepIndex: number) => {
        if (currentStatusIndex !== -1 && stepIndex < currentStatusIndex) return 'text-green-600';
        if (currentStatusIndex !== -1 && stepIndex === currentStatusIndex) return 'text-blue-600 font-medium';
        return 'text-gray-400';
    };

    return (
        <Timeline>
            {statuses.map((step, index) => (
                <TimelineItem key={step.key}>
                    {index < statuses.length - 1 && <TimelineConnector />}
                    <TimelineHeader>
                        <TimelineIcon>{getStatusIcon(index)}</TimelineIcon>
                        <TimelineTitle className={`text-sm ${getStatusColor(index)}`}>{step.label}</TimelineTitle>
                    </TimelineHeader>
                    <TimelineBody />
                </TimelineItem>
            ))}
        </Timeline>
    );
}
