<?php
namespace App\Helpers;

/**
 * Avatar Helper - Standardized image display throughout the application
 * 
 * Provides consistent avatar/image rendering with fallback to initials
 * Usage: Avatar::render($data) or Avatar::small($data, 'user')
 */
class Avatar
{
    /**
     * Render avatar with image or initials fallback
     * 
     * @param array $data Member/User data array with 'avatar', 'first_name', 'last_name'
     * @param string $size CSS size class: 'sm' (w-9 h-9), 'md' (w-12 h-12), 'lg' (w-16 h-16)
     * @param string $type Image type: 'avatar' (members) or 'logo' (org)
     * @return string HTML markup
     */
    public static function render(array $data, string $size = 'sm', string $type = 'avatar'): string
    {
        $sizes = [
            'sm' => ['w-9', 'h-9', 'text-xs'],
            'md' => ['w-12', 'h-12', 'text-sm'],
            'lg' => ['w-16', 'h-16', 'text-base'],
        ];

        $sz = $sizes[$size] ?? $sizes['sm'];
        
        // Determine image path based on type
        if ($type === 'logo') {
            $imagePath = $data['logo'] ?? null;
            $folder = 'logos';
            $fallbackName = $data['org_name'] ?? 'A';
        } else {
            $imagePath = $data['avatar'] ?? null;
            $folder = 'avatars';
            $fallbackName = ($data['first_name'] ?? 'U') . ' ' . ($data['last_name'] ?? '');
        }

        // If image exists, display it
        if ($imagePath) {
            return sprintf(
                '<img src="%s/storage/uploads/%s/%s" class="%s %s rounded-full object-cover border-2 border-slate-100" alt="" loading="lazy">',
                APP_URL,
                $folder,
                htmlspecialchars($imagePath),
                $sz[0],
                $sz[1]
            );
        }

        // Fallback: initials in circle
        $initials = self::getInitials($fallbackName);
        return sprintf(
            '<div class="avatar-circle %s %s text-center flex items-center justify-center bg-gradient-to-br from-blue-400 to-purple-500 text-white font-semibold rounded-full border-2 border-slate-100">%s</div>',
            $sz[0],
            $sz[1],
            htmlspecialchars($initials)
        );
    }

    /**
     * Shorthand for small avatar (w-9 h-9)
     */
    public static function small(array $data, string $type = 'avatar'): string
    {
        return self::render($data, 'sm', $type);
    }

    /**
     * Shorthand for medium avatar (w-12 h-12)
     */
    public static function medium(array $data, string $type = 'avatar'): string
    {
        return self::render($data, 'md', $type);
    }

    /**
     * Shorthand for large avatar (w-16 h-16)
     */
    public static function large(array $data, string $type = 'avatar'): string
    {
        return self::render($data, 'lg', $type);
    }

    /**
     * Extract initials from name (e.g., "John Doe" → "JD")
     */
    public static function getInitials(string $name): string
    {
        $parts = array_filter(explode(' ', trim($name)));
        if (empty($parts)) return 'A';
        
        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 1));
        }
        
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }

    /**
     * Get organization logo with fallback
     */
    public static function orgLogo(array $settings = []): string
    {
        $logo = $settings['org_logo'] ?? null;
        $orgName = $settings['org_name'] ?? 'Akabbo';

        if ($logo) {
            return sprintf(
                '<img src="%s/storage/uploads/logos/%s" class="h-8" alt="%s" loading="lazy">',
                APP_URL,
                htmlspecialchars($logo),
                htmlspecialchars($orgName)
            );
        }

        // Fallback: text logo
        return sprintf(
            '<div class="text-sm font-bold text-blue-600">%s</div>',
            htmlspecialchars(substr($orgName, 0, 3))
        );
    }

    /**
     * Get favicon path (with fallback to default)
     */
    public static function favicon(array $settings = []): string
    {
        $favicon = $settings['org_favicon'] ?? null;
        if ($favicon) {
            return APP_URL . '/storage/uploads/logos/' . htmlspecialchars($favicon);
        }
        
        // ✅ FIX: Fallback to org_logo if no specific favicon is set
        $logo = $settings['org_logo'] ?? null;
        if ($logo) {
            return APP_URL . '/storage/uploads/logos/' . htmlspecialchars($logo);
        }
        
        return APP_URL . '/public/assets/img/favicon.ico'; // Ensure this default exists
    }
}
