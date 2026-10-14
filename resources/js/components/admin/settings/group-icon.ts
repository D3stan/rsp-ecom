import {
    Boxes,
    LayoutGrid,
    Mail,
    MapPin,
    Percent,
    Phone,
    Scale,
    Search,
    Server,
    Settings,
    Share2,
    ShieldCheck,
    ShoppingCart,
    Star,
    Store,
    Truck,
    type LucideIcon,
} from 'lucide-react';

// The icon names used by `groups` in config/settings.php. A new name falls back to the generic icon.
const GROUP_ICONS: Record<string, LucideIcon> = {
    Store,
    Phone,
    Share2,
    Percent,
    Truck,
    Scale,
    ShieldCheck,
    ShoppingCart,
    Boxes,
    LayoutGrid,
    Star,
    Mail,
    MapPin,
    Search,
    Server,
};

export function groupIcon(name: string | null): LucideIcon {
    return (name && GROUP_ICONS[name]) || Settings;
}
