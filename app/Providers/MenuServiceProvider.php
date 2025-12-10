<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;

class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // get all data from menu.json file
        $verticalMenuJson = file_get_contents(base_path('resources/data/menu-data/mainMenu.json'));
        $verticalMenuData = json_decode($verticalMenuJson);
        $horizontalMenuJson = file_get_contents(base_path('resources/data/menu-data/horizontalMenu.json'));
        $horizontalMenuData = json_decode($horizontalMenuJson);

        // Pre-translate menu items
        $verticalMenuData = $this->translateMenuItems($verticalMenuData);
        $horizontalMenuData = $this->translateMenuItems($horizontalMenuData);

        $self = $this;
        \View::composer('*', function ($view) use ($verticalMenuData, $horizontalMenuData, $self) {
            $verticalMenuCopy = json_decode(json_encode($verticalMenuData));
            if (Auth::check() && Auth::user()->hasRole('user')) {
                $verticalMenuCopy = $self->filterMenuForUserRole($verticalMenuCopy);
            }
            $view->with('menuData', [$verticalMenuCopy, $horizontalMenuData]);
        });
    }

    /**
     * Recursively translate menu items
     */
    private function translateMenuItems($menuData)
    {
        if (isset($menuData->menu)) {
            foreach ($menuData->menu as $menu) {
                if (isset($menu->name)) {
                    $menu->name = __('locale.' . $menu->name);
                }
                if (isset($menu->navheader)) {
                    $menu->navheader = __('locale.' . $menu->navheader);
                }
                if (isset($menu->submenu)) {
                    $this->translateMenuItems((object)['menu' => $menu->submenu]);
                }
            }
        }
        return $menuData;
    }

    private function filterMenuForUserRole($menuData)
    {
        if (!isset($menuData->menu) || empty($menuData->menu)) {
            return $menuData;
        }

        $filteredMenu = [];
        foreach ($menuData->menu as $menu) {
            if (isset($menu->name) && $menu->name === 'Radni nalozi') {
                $filteredMenu[] = $menu;
                break;
            }
        }

        $menuData->menu = $filteredMenu;
        return $menuData;
    }
}
