<?php

public function getTopProfile($profiles, $identities = [], $fullName = null)
{
    info('public function getTopProfile', ['identities' => $identities, 'profiles' => $profiles, 'fullName' => $fullName]);

    if(empty($profiles) || !is_array($profiles)){
        return ['topProfile' => null, 'multiple_profile_found' => 0];
    }

    /* ------------- STEP 1 ------------- */
    info('getTopProfile step 1 (1.1) START', ['fullName' => $fullName]);
    // Cek Full Name Identifier sama dengan Name yang di kasih watt
    if(!empty($fullName)){
        $fullName = trim(strtolower($fullName));
        info('getTopProfile step 1 (1.2)', ['fullName' => $fullName]);
        foreach($profiles as $profile){
            $domains = $profile['domains'] ?? [];
            $names = $domains['name'] ?? [];
            info('getTopProfile step 1 (1.3)', ['domains' => $domains, 'names' => $names]);
            
            foreach($names as $name){
                info('getTopProfile step 1 (1.4)', ['trim_strtolower_name' => trim(strtolower($name)), 'fullName' => $fullName]);
                if(trim(strtolower($name)) === $fullName){
                    info('getTopProfile step 1 MATCH (1.5)');
                    return ['topProfile' => $profile, 'multiple_profile_found' => 0];
                }
            }
        }
    }
    info('getTopProfile step 1 (1.6) END');
    /* ------------- STEP 1 ------------- */



    /* ------------- STEP 2 ------------- */
    // Buat mapping overall_quality_score dari identities berdasarkan person_id
    info('getTopProfile step 2 (2.1.1) START', ['identities' => $identities]);
    $qualityScoreMap = [];
    if(!empty($identities) && is_array($identities)){
        info('getTopProfile step 2 (2.1.2)');
        foreach($identities as $identity){
            $personId = $identity['person_id'] ?? null;
            $overallQualityScore = $identity['overall_quality_score'] ?? 0;
            info('getTopProfile step 2 (2.1.3)', ['personId' => $personId, 'overallQualityScore' => $overallQualityScore]);
            if($personId !== null){
                // Convert person_id ke string untuk memastikan matching (karena profiles menggunakan string)
                $qualityScoreMap[(string)$personId] = (int) $overallQualityScore;
                info('getTopProfile step 2 (2.1.4)', ['qualityScoreMap' => $qualityScoreMap]);
            }
        }
    }
    info('getTopProfile step 2 (2.1.5) END', ['qualityScoreMap' => $qualityScoreMap]);
    
    // Hitung quality score untuk setiap profile menggunakan overall_quality_score
    info('getTopProfile step 2 (2.2.1) START', ['profiles' => $profiles]);
    $profilesWithScore = [];
    foreach($profiles as $profile){
        $personId = $profile['person_id'] ?? null;
        $score = 0;
        info('getTopProfile step 2 (2.2.2)', ['personId' => $personId, 'qualityScoreMap' => $qualityScoreMap]);
        // Gunakan overall_quality_score dari identities jika ada
        if($personId !== null && isset($qualityScoreMap[(string)$personId])){
            $score = (int) $qualityScoreMap[(string)$personId];
            info('getTopProfile step 2 (2.2.3)', ['score' => $score]);
        }
        
        $profilesWithScore[] = [
            'profile' => $profile,
            'score' => $score
        ];
        info('getTopProfile step 2 (2.2.4)', ['score' => $score, 'profilesWithScore' => $profilesWithScore]);
    }
    info('getTopProfile step 2 (2.2.5) END', ['profilesWithScore' => $profilesWithScore]);

    info('getTopProfile step 2 (2.3.1)', ['profilesWithScore' => $profilesWithScore]);
    if(empty($profilesWithScore)){
        info('getTopProfile step 2 (2.3.2)');
        return ['topProfile' => null, 'multiple_profile_found' => 0];
    }

    // Urutkan berdasarkan score tertinggi
    usort($profilesWithScore, function($a, $b){
        return $b['score'] - $a['score'];
    });
    $maxScore = $profilesWithScore[0]['score'];
    info('getTopProfile step 2 (2.4.1)', ['maxScore' => $maxScore, 'profilesWithScore' => $profilesWithScore]);

    // Cek apakah ada lebih dari 1 profile dengan score yang sama
    $topScoredProfiles = array_filter($profilesWithScore, function($item) use ($maxScore){
        return $item['score'] == $maxScore;
    });
    info('getTopProfile step 2 (2.5.1)', ['topScoredProfiles' => $topScoredProfiles]);

    // Jika hanya 1 profile dengan score tertinggi
    if(count($topScoredProfiles) == 1){
        info('getTopProfile step 2 (2.6.1)');
        return ['topProfile' => $topScoredProfiles[0]['profile'], 'multiple_profile_found' => 0];
    }
    /* ------------- STEP 2 ------------- */
    


    /* ------------- STEP 3 ------------- */
    // Jika lebih dari 1 profile dengan score yang sama
    $topScoredProfiles = array_values($topScoredProfiles);
    info('getTopProfile step 3 (3.1)', ['topScoredProfiles' => $topScoredProfiles, 'count_topScoredProfiles' => count($topScoredProfiles)]);
    
    // Weighting untuk setiap domain
    $domainWeights = ['email' => 5, 'phone' => 2, 'address' => 2, 'name' => 1];
    info('getTopProfile step 3 (3.2)', ['domainWeights' => $domainWeights]);

    // Hitung weighted score untuk setiap profile
    info('getTopProfile step 3 (3.3.1) START - only 2 profiles');
    $maxWeightedScore = 0;
    foreach($topScoredProfiles as $item){
        $profile = $item['profile'];
        $domains = $profile['domains'] ?? [];
        $weightedScore = (count($domains['email'] ?? []) * $domainWeights['email']) +
                            (count($domains['phone'] ?? []) * $domainWeights['phone']) +
                            (count($domains['address'] ?? []) * $domainWeights['address']) +
                            (count($domains['name'] ?? []) * $domainWeights['name']);
        info('getTopProfile step 3 (3.3.2)', [
            'email' => count($domains['email'] ?? []) . " x " . $domainWeights['email'] . " = " . (count($domains['email'] ?? []) * $domainWeights['email']),
            'phone' => count($domains['phone'] ?? []) . " x " . $domainWeights['phone'] . " = " . (count($domains['phone'] ?? []) * $domainWeights['phone']),
            'address' => count($domains['address'] ?? []) . " x " . $domainWeights['address'] . " = " . (count($domains['address'] ?? []) * $domainWeights['address']),
            'name' => count($domains['name'] ?? []) . " x " . $domainWeights['name'] . " = " . (count($domains['name'] ?? []) * $domainWeights['name']),
            'weightedScore' => $weightedScore,
            'maxWeightedScore' => $maxWeightedScore,
        ]);
        if($weightedScore > $maxWeightedScore){
            $maxWeightedScore = $weightedScore;
            info('getTopProfile step 3 (3.3.3)', ['maxWeightedScore' => $maxWeightedScore, 'weightedScore' => $weightedScore]);
        }
    }
    info('getTopProfile step 3 (3.3.4)', ['maxWeightedScore' => $maxWeightedScore]);

    info('getTopProfile step 3 (3.3.5)');
    $profilesWithWeight = [];
    foreach($topScoredProfiles as $item){
        $profile = $item['profile'];
        $domains = $profile['domains'] ?? [];
        $weightedScore = (count($domains['email'] ?? []) * $domainWeights['email']) +
                            (count($domains['phone'] ?? []) * $domainWeights['phone']) +
                            (count($domains['address'] ?? []) * $domainWeights['address']) +
                            (count($domains['name'] ?? []) * $domainWeights['name']);
        info('getTopProfile step 3 (3.3.6)', [
            'email' => count($domains['email'] ?? []) . " x " . $domainWeights['email'] . " = " . (count($domains['email'] ?? []) * $domainWeights['email']),
            'phone' => count($domains['phone'] ?? []) . " x " . $domainWeights['phone'] . " = " . (count($domains['phone'] ?? []) * $domainWeights['phone']),
            'address' => count($domains['address'] ?? []) . " x " . $domainWeights['address'] . " = " . (count($domains['address'] ?? []) * $domainWeights['address']),
            'name' => count($domains['name'] ?? []) . " x " . $domainWeights['name'] . " = " . (count($domains['name'] ?? []) * $domainWeights['name']),
            'weightedScore' => $weightedScore,
            'profile' => $profile,
            'domains' => $domains,
        ]);
        $profilesWithWeight[] = [
            'profile' => $profile,
            'weightedScore' => $weightedScore
        ];
        info('getTopProfile step 3 (3.3.7)', ['profilesWithWeight' => $profilesWithWeight]);
    }

    // Urutkan berdasarkan weightedScore tertinggi (GAP-based)
    usort($profilesWithWeight, function($a, $b){
        return $b['weightedScore'] - $a['weightedScore'];
    });
    info('getTopProfile step 3 (3.3.8)', ['profilesWithWeight' => $profilesWithWeight]);

    // hitung multiple_profile_found jika terdapat 2 data profile
    $multipleProfileFound = 1;
    if(count($topScoredProfiles) == 2){
        // Ambil dua skor teratas
        $score1 = $profilesWithWeight[0]['weightedScore']; // tertinggi
        $score2 = $profilesWithWeight[1]['weightedScore']; // kedua
        info('getTopProfile step 3 (3.3.9)', ['score1' => $score1, 'score2' => $score2]);

        // Hitung GAP eksplisit
        $thresholdGap = 60;
        $gap = 0;
        if($score1 > 0){
            $gap = (($score1 - $score2) / $score1) * 100;
        }
        info('getTopProfile step 3 (3.3.10)', ['gap' => "{$gap} %"]);

        // Penentuan multiple_profile_found
        $multipleProfileFound = ($gap >= $thresholdGap) ? 0 : 1;
        info('getTopProfile step 3 (3.3.11)', ['multipleProfileFound' => $multipleProfileFound]);
    }
    /* ------------- STEP 3 ------------- */

    // return result
    return ['topProfile' => $profilesWithWeight[0]['profile'], 'multiple_profile_found' => 1];
}