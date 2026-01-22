/**
    * Calculate optimal batch size based on audience limit
    * @param int $audienceLimit
    * @return int
    */
public function calculateOptimalBatchSize(int $audience)
{
    // Case 1: audience kecil → 1 batch
    if($audience <= 100){
        return 100;
    }

    // Boundary
    $minAudience = 100;
    $maxAudience = 4000;

    $minBatch = 100;
    $maxBatch = 400;

    // Clamp audience supaya aman
    $audience = max($minAudience, min($maxAudience, $audience));

    // Linear scaling
    $batchSize = $minBatch + (($audience - $minAudience) * ($maxBatch - $minBatch) / ($maxAudience - $minAudience));

    // Optional: bulatkan ke kelipatan 50 biar rapi
    $batchSize = round($batchSize / 50) * 50;

    return (int) $batchSize;
}