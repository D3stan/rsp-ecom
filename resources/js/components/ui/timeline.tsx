
import * as React from 'react';
import { cn } from '@/lib/utils';

type TimelineProps = React.ComponentProps<'ol'>;

const Timeline = React.forwardRef<HTMLOListElement, TimelineProps>(
    ({ className, ...props }, ref) => {
        return (
            <ol
                ref={ref}
                className={cn('flex flex-col', className)}
                {...props}
            />
        );
    }
);
Timeline.displayName = 'Timeline';

type TimelineItemProps = React.ComponentProps<'li'>;

const TimelineItem = React.forwardRef<HTMLLIElement, TimelineItemProps>(
    ({ className, ...props }, ref) => {
        return (
            <li
                ref={ref}
                className={cn('relative flex flex-col', className)}
                {...props}
            />
        );
    }
);
TimelineItem.displayName = 'TimelineItem';

type TimelineTimeProps = React.ComponentProps<'p'>;

const TimelineTime = React.forwardRef<HTMLParagraphElement, TimelineTimeProps>(
    ({ className, ...props }, ref) => {
        return (
            <p
                ref={ref}
                className={cn(
                    'absolute left-0 top-[calc(1.375rem-0.5em)] -translate-x-[calc(100%+1.5rem)] text-sm text-muted-foreground',
                    className
                )}
                {...props}
            />
        );
    }
);
TimelineTime.displayName = 'TimelineTime';

type TimelineConnectorProps = React.ComponentProps<'div'>;

const TimelineConnector = React.forwardRef<
    HTMLDivElement,
    TimelineConnectorProps
>(({ className, ...props }, ref) => {
    return (
        <div
            ref={ref}
            className={cn(
                'absolute left-[1.375rem] top-[1.375rem] h-full w-px -translate-x-1/2 bg-border',
                className
            )}
            {...props}
        />
    );
});
TimelineConnector.displayName = 'TimelineConnector';

type TimelineHeaderProps = React.ComponentProps<'div'>;

const TimelineHeader = React.forwardRef<HTMLDivElement, TimelineHeaderProps>(
    ({ className, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={cn('flex items-center gap-x-4', className)}
                {...props}
            />
        );
    }
);
TimelineHeader.displayName = 'TimelineHeader';

type TimelineIconProps = React.ComponentProps<'div'>;

const TimelineIcon = React.forwardRef<HTMLDivElement, TimelineIconProps>(
    ({ className, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={cn(
                    'relative z-10 flex size-11 items-center justify-center rounded-full bg-background',
                    className
                )}
                {...props}
            >
                <div className="size-5 rounded-full border-2 border-primary bg-background" />
            </div>
        );
    }
);
TimelineIcon.displayName = 'TimelineIcon';

type TimelineTitleProps = React.ComponentProps<'h3'>;

const TimelineTitle = React.forwardRef<
    HTMLHeadingElement,
    TimelineTitleProps
>(({ className, ...props }, ref) => {
    return (
        <h3
            ref={ref}
            className={cn('font-semibold', className)}
            {...props}
        />
    );
});
TimelineTitle.displayName = 'TimelineTitle';

type TimelineBodyProps = React.ComponentProps<'div'>;

const TimelineBody = React.forwardRef<HTMLDivElement, TimelineBodyProps>(
    ({ className, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={cn('pb-12 pl-[3.75rem] pt-4', className)}
                {...props}
            />
        );
    }
);
TimelineBody.displayName = 'TimelineBody';

export {
    Timeline,
    TimelineItem,
    TimelineTime,
    TimelineConnector,
    TimelineHeader,
    TimelineIcon,
    TimelineTitle,
    TimelineBody,
};
