import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTranslation from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { type SettingGroup } from '@/types';
import { groupIcon } from './group-icon';

interface GroupNavProps {
    groups: SettingGroup[];
    activeKey: string;
    /** key of the group with unsaved changes (shows a dot) */
    dirtyKey: string | null;
    onSelect: (key: string) => void;
}

/** Group list: a vertical nav on desktop, a select on small screens. */
export function GroupNav({ groups, activeKey, dirtyKey, onSelect }: GroupNavProps) {
    const { t } = useTranslation();

    return (
        <>
            <div className="lg:hidden">
                <Select value={activeKey} onValueChange={onSelect}>
                    <SelectTrigger className="w-full" aria-label={t('settings.ui.nav_label')}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {groups.map((group) => {
                            const Icon = groupIcon(group.icon);

                            return (
                                <SelectItem key={group.key} value={group.key}>
                                    <Icon className="size-4" aria-hidden="true" />
                                    {group.label}
                                </SelectItem>
                            );
                        })}
                    </SelectContent>
                </Select>
            </div>

            <nav aria-label={t('settings.ui.nav_label')} className="hidden lg:block">
                <ul className="space-y-1">
                    {groups.map((group) => {
                        const Icon = groupIcon(group.icon);
                        const active = group.key === activeKey;

                        return (
                            <li key={group.key}>
                                <button
                                    type="button"
                                    onClick={() => onSelect(group.key)}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-left text-sm transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                        active
                                            ? 'bg-accent font-medium text-accent-foreground'
                                            : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-4 shrink-0" aria-hidden="true" />
                                    <span className="min-w-0 flex-1 truncate">{group.label}</span>
                                    {dirtyKey === group.key && <span className="size-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true" />}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </nav>
        </>
    );
}
