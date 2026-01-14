<?php


// AGENCY SIDEBAR SETTING
$agencysidebar = "";
$rootsidebarleadmenu = "";
if (!empty(trim($params['ownedcompanyid']))) {
    $rootcompanysetting = CompanySetting::where('company_id',trim($params['idsys']))->whereEncrypted('setting_name','rootcustomsidebarleadmenu')->get();
    if (count($rootcompanysetting) > 0) {
        $rootsidebarleadmenu = json_decode($rootcompanysetting[0]['setting_value']);

        if ($params['ownedcompanyid'] != $params['idsys']) {
            $agencysidebar = $this->getcompanysetting($params['ownedcompanyid'], 'agencysidebar');
            $exist_setting = $this->getcompanysetting($params['idsys'], 'rootexistagencymoduleselect');
            $selectedModulesTypes = []; // Untuk menyimpan tipe modul yang ada di SelectedModules
            
            if (!empty($agencysidebar) && isset($agencysidebar->SelectedModules)) {
                foreach ($agencysidebar->SelectedModules as $key => $value) {
                    $selectedModulesTypes[] = $value->type; // Simpan tipe modul
                    foreach ($rootsidebarleadmenu as $key1 => $value1) {
                        if ($key1 == $value->type && $value->status == false) {
                            unset($rootsidebarleadmenu->$key1);
                        }
                    }
                }
            } elseif (!empty($exist_setting) && isset($exist_setting->SelectedModules)) {
                foreach ($exist_setting->SelectedModules as $key => $value) {
                    $selectedModulesTypes[] = $value->type; // Simpan tipe modul
                    foreach ($rootsidebarleadmenu as $key2 => $value2) {
                        if ($key2 == $value->type && $value->status == false) {
                            unset($rootsidebarleadmenu->$key2);
                        }
                    }
                }
            }
            
            // Jika predict tidak ada di SelectedModules, hapus dari rootsidebarleadmenu (default false)
            if (!in_array('predict', $selectedModulesTypes) && isset($rootsidebarleadmenu->predict)) {
                unset($rootsidebarleadmenu->predict);
            }
        }

        $agencysidebar = $rootsidebarleadmenu;
    }
}
info('', [
    'agencysidebar' => $agencysidebar,
    'ownedcompanyid' => $params['ownedcompanyid'],
    'idsys' => $params['idsys'],
]);
// AGENCY SIDEBAR SETTING