<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The links in the header menu, worked out for whoever is looking at the page.
 *
 * The menu is a list of sections. A section with a label is a dropdown on wide screens and a headed group
 * in the ☰ menu on phones; a section without a label is shown as plain links. Every visitor gets a
 * Memories link, so the /memories page is one tap away from anywhere.
 *
 * layouts/app.blade.php receives the sections as $menuSections (see AppServiceProvider) and only displays them.
 *
 * @phpstan-type MenuLink array{url: string, label: string, isActive: bool}
 * @phpstan-type MenuSection array{label: ?string, links: array<int, MenuLink>, isActive: bool}
 */
class MainMenu
{
    public function __construct(private Request $request) {}

    /**
     * Picks the menu for this visitor: guest, member (portal) or admin.
     *
     * @return array<int, MenuSection>
     */
    public function sectionsFor(?User $user): array
    {
        if ($user === null) {
            return $this->guestMenu();
        }

        if ($this->showsMemberMenu($user)) {
            return $this->memberMenu($user);
        }

        return $this->adminMenu($user);
    }

    /**
     * A member sees the portal menu. A member who is also an admin sees it only on portal pages,
     * and the admin menu everywhere else.
     */
    private function showsMemberMenu(User $user): bool
    {
        if (! $user->isAdmin()) {
            return true;
        }

        return $user->isStudent() && $this->request->routeIs('portal.*');
    }

    /**
     * Guests have no menu bar: these links sit next to Login (and in the ☰ menu on phones),
     * so keep them in one section without a label.
     *
     * @return array<int, MenuSection>
     */
    private function guestMenu(): array
    {
        return [
            $this->section(null, [
                $this->memoriesLink(),
            ]),
        ];
    }

    /** @return array<int, MenuSection> */
    private function memberMenu(User $user): array
    {
        $links = [
            $this->link('portal.dashboard', 'Home', 'portal.dashboard'),
            $this->link('portal.announcements', 'Notice', 'portal.announcements*'),
            $this->memoriesLink(),
            $this->link('portal.payments', 'My Payments', 'portal.payments'),
            $this->link('portal.profile', 'Profile', 'portal.profile'),
        ];

        if ($user->isAdmin()) {
            $links[] = $this->link('admin.dashboard', 'Admin panel', 'admin.*');
        }

        return [$this->section(null, $links)];
    }

    /** @return array<int, MenuSection> */
    private function adminMenu(User $user): array
    {
        $sections = [
            $this->section(null, [
                $this->link('admin.dashboard', 'Dashboard', 'admin.dashboard'),
                $this->memoriesLink(),
            ]),
            $this->section('Members', [
                $this->link('admin.students.index', 'Students', 'admin.students.*'),
                $this->link('admin.student-fields.index', 'Student fields', 'admin.student-fields.*'),
            ]),
            $this->section('Events', [
                $this->link('admin.events.index', 'Events', 'admin.events.*'),
                $this->link('admin.payments.index', 'Payments', 'admin.payments.*'),
            ]),
            $this->section('Communication', [
                $this->link('admin.announcements.index', 'Announcements', 'admin.announcements.*'),
                $this->link('admin.memories.index', 'Memories', 'admin.memories.*'),
                $this->link('admin.member-emails.create', 'Email members', 'admin.member-emails.*'),
                $this->link('admin.settings.edit', 'Welcome page', 'admin.settings.*'),
            ]),
        ];

        if ($user->isStudent()) {
            $sections[] = $this->section(null, [
                $this->link('portal.dashboard', 'My portal', 'portal.*'),
            ]);
        }

        return $sections;
    }

    /**
     * The public Memories page, in every visitor's menu. "memories.*" also covers a single post's page,
     * but not the admin's "admin.memories.*" pages.
     *
     * @return MenuLink
     */
    private function memoriesLink(): array
    {
        return $this->link('memories.index', 'Memories ⚡', 'memories.*');
    }

    /**
     * One menu link. $activeOn is a route name pattern: the link is highlighted on the pages it matches.
     *
     * @return MenuLink
     */
    private function link(string $routeName, string $label, string $activeOn): array
    {
        return [
            'url' => route($routeName),
            'label' => $label,
            'isActive' => $this->request->routeIs($activeOn),
        ];
    }

    /**
     * A group of links. It is highlighted when one of its links is.
     *
     * @param  array<int, MenuLink>  $links
     * @return MenuSection
     */
    private function section(?string $label, array $links): array
    {
        return [
            'label' => $label,
            'links' => $links,
            'isActive' => collect($links)->contains('isActive', true),
        ];
    }
}
