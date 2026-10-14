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
