---
title: ICP Analysis Workflow
description: Learn how to analyze your customer base to identify defining characteristics and find lookalike audiences.
---

# ICP Analysis Workflow

**[Download N8N Workflow Template](/workflows/icp-analysis.json)**

## Overview

The Ideal Customer Profile (ICP) Analysis workflow demonstrates how to analyze your existing customer base to identify defining characteristics, then use those insights to find lookalike audiences. This is a multi-step process that combines identity resolution, profile enrichment, cluster analysis, and audience discovery.

**For no-code automation**: The [N8N Workflow](/docs/workflows/icp-analysis) provides an automated version with AI-driven cluster analysis using Claude Sonnet 4.5.

## Use Case

**Goal:** Given a list of customer identifiers (emails, phone numbers, or addresses), identify the common characteristics of your best customers and find similar people in the broader population.

**Business Value:**
- Understand what makes your customers similar
- Identify market segments and personas
- Build lookalike audiences for marketing campaigns
- Optimize customer acquisition targeting

## Workflow Overview

```mermaid
%%{init: {'theme':'base', 'themeVariables': {'fontSize':'14px'}}}%%
flowchart TD
    A["CSV with Customer Identifiers"]
    B["Step 1: resolve_identities<br/>Multi-identifier resolution"]
    C["Person IDs with Quality Scores"]
    D["Step 2: get_person<br/>Enrich with all domains"]
    E["Enriched Profiles"]
    F["Step 3: Analyze Attributes<br/>• Count frequency<br/>• Find intersections<br/>• Build clusters"]
    G["Top Attributes &<br/>Multi-Attribute Clusters"]
    H["Step 4: list_clusters<br/>Map attributes to cluster IDs"]
    I["Cluster IDs with Metadata"]
    J{"Step 5: Targeting<br/>Strategy?"}
    K["Precision<br/>(AND logic)<br/>Smaller audience"]
    L["Reach<br/>(OR logic)<br/>Larger audience"]
    M["Hybrid<br/>(Mixed logic)<br/>Balanced audience"]
    N["Step 5: find_persons<br/>Execute boolean expression"]
    O["Lookalike Audience"]
    P["Step 6: get_person<br/>(optional validation)"]
    Q["Ready for Activation"]

    A --> B
    B --> C
    C --> D
    D --> E
    E --> F
    F --> G
    G --> H
    H --> I
    I --> J
    J --> K
    J --> L
    J --> M
    K --> N
    L --> N
    M --> N
    N --> O
    O --> P
    P --> Q
    O -.->|Skip validation| Q

    classDef default padding:15px
    style A fill:#e1f5ff
    style G fill:#fff3cd
    style J fill:#f8d7da
    style Q fill:#d4edda
```

## Workflow Steps

### Step 1: Resolve Identifiers to Person IDs

Convert your customer identifiers (emails, phones, addresses) into standardized person IDs that can be used across the platform.

**Tool:** `resolve_identities`

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "resolve_identities",
    "arguments": {
      "multi_identifiers": [
        {
          "id_type": "email",
          "hash_type": "plaintext",
          "values": [
            "customer1@example.com",
            "customer2@example.com",
            "customer3@example.com"
          ]
        },
        {
          "id_type": "phone",
          "hash_type": "plaintext",
          "values": [
            "5551234567",
            "5559876543"
          ]
        }
      ],
      "format": "json"
    }
  },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "identities": [],
    "stats": {
      "requested": 5,
      "resolved": 4,
      "rate": 0.8
    },
    "export": {
      "url": "https://s3.amazonaws.com/presigned-url...",
      "format": "json",
      "rows": 4,
      "size_bytes": 2048,
      "expires_at": "2025-11-20T13:00:00.000Z"
    },
    "tool_trace_id": "abc123...",
    "workflow_id": "550e8400-e29b-41d4-a716-446655440000"
  },
  "id": 1
}
```

**Download the JSON export** from `export.url` to get the full identity resolution data:
```json
[
  {
    "person_id": 12345,
    "overall_quality_score": 0.95,
    "matches": [
      {
        "criterion_type": "email_plaintext",
        "criterion_value": "customer1@example.com",
        "quality_score": 0.95
      }
    ],
    "identifiers": {
      "email": ["customer1@example.com", "alt1@example.com"],
      "phone": ["5551234567"]
    }
  },
  {
    "person_id": 67890,
    "overall_quality_score": 0.88,
    "matches": [
      {
        "criterion_type": "email_plaintext",
        "criterion_value": "customer2@example.com",
        "quality_score": 0.88
      }
    ],
    "identifiers": {
      "email": ["customer2@example.com"]
    }
  }
]
```

**Key Points:**
- Use `multi_identifiers` to search across multiple identifier types simultaneously
- `format: "json"` returns a download URL for the full dataset (use `format: "none"` for inline results with small datasets)
- Filter results by `overall_quality_score` (e.g., >= 0.5) to ensure match quality
- The resolved `person_id` values are used in subsequent enrichment steps

---

### Step 2: Enrich Person Profiles

Retrieve detailed demographic, behavioral, and interest data for the resolved person IDs.

**Tool:** `get_person`

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "get_person",
    "arguments": {
      "person_ids": [12345, 67890, 11111, 22222],
      "domains": [
        "address", "affinity", "content", "demographic", "email",
        "employment", "financial", "household", "id", "intent_category",
        "intent_topic", "interest", "lifestyle", "maid", "name",
        "phone", "political", "purchase"
      ],
      "format": "none"
    }
  },
  "id": 2
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "profiles": [
      {
        "person_id": "12345",
        "metadata": {
          "quality_score": 0.92,
          "last_modified": "2025-01-15T10:30:00Z"
        },
        "domains": {
          "gender": "Female",
          "generation": "Millennial",
          "interested_fitness": "Yes",
          "interested_healthy_living": "Yes",
          "fitness_affinity": "High",
          "household_income_range": "$75K-$100K",
          "email1": "customer1@example.com",
          "email2": "alt1@example.com",
          "phone1": "5551234567",
          "first_name": "Jane",
          "last_name": "Smith"
        }
      },
      {
        "person_id": "67890",
        "metadata": {
          "quality_score": 0.88
        },
        "domains": {
          "gender": "Female",
          "generation": "Gen X",
          "interested_healthy_living": "Yes",
          "interested_outdoors": "Yes",
          "email1": "customer2@example.com",
          "first_name": "Sarah"
        }
      }
    ],
    "tool_trace_id": "def456...",
    "workflow_id": "550e8400-e29b-41d4-a716-446655440000"
  },
  "id": 2
}
```

**Key Points:**
- Request all available domains to get a comprehensive view
- The `domains` field contains attribute key-value pairs (e.g., "gender": "Female")
- Attributes are returned as flat key-value pairs, not nested by domain category
- Multi-value fields like email and phone are numbered (email1, email2, email3, etc.)
- To use attributes in `find_persons`, you need to map them to cluster IDs using `list_clusters`
- The `profiles` array is always populated, even when using export format
- Batch requests support up to 1000 person IDs per call

---

### Step 3: Analyze Attribute Patterns and Intersections

Aggregate the enriched profiles to identify not just common characteristics, but combinations of attributes that frequently co-occur. This reveals the true ICP by finding the intersection of traits.

**Analysis Logic:**
```javascript
const profiles = [...]; // From get_person response
const totalPersons = profiles.length;

// Step 1: Extract attribute sets for each person
const personAttributeSets = profiles.map(person => {
  if (!person.domains) return [];

  const attributes = [];
  for (const [name, value] of Object.entries(person.domains)) {
    // Skip contact info and identifiers
    if (name.startsWith('email') || name.startsWith('phone') ||
        name.startsWith('address') || name === 'first_name' ||
        name === 'last_name') {
      continue;
    }
    attributes.push(\`\${name}=\${value}\`);
  }
  return attributes;
});

// Step 2: Count individual attribute frequencies
const attributeCounts = {};
for (const attributes of personAttributeSets) {
  for (const attr of attributes) {
    attributeCounts[attr] = (attributeCounts[attr] || 0) + 1;
  }
}

// Step 3: Find top individual attributes (baseline)
const topAttributes = Object.entries(attributeCounts)
  .map(([attr, count]) => ({
    attribute: attr,
    count,
    percentage: (count / totalPersons * 100).toFixed(2)
  }))
  .sort((a, b) => b.count - a.count)
  .slice(0, 20); // Get top 20 for intersection analysis

// Step 4: Find highly intersecting attribute combinations
const attributeIntersections = [];

// Test all pairs from top attributes
for (let i = 0; i < Math.min(10, topAttributes.length); i++) {
  for (let j = i + 1; j < Math.min(10, topAttributes.length); j++) {
    const attr1 = topAttributes[i].attribute;
    const attr2 = topAttributes[j].attribute;

    // Count co-occurrence
    let intersectionCount = 0;
    for (const attributes of personAttributeSets) {
      if (attributes.includes(attr1) && attributes.includes(attr2)) {
        intersectionCount++;
      }
    }

    // Calculate intersection strength
    const intersectionRate = intersectionCount / totalPersons;
    const expectedIntersection = (topAttributes[i].count / totalPersons) *
                                  (topAttributes[j].count / totalPersons);
    const liftScore = intersectionRate / expectedIntersection;

    if (intersectionCount > totalPersons * 0.3) { // At least 30% intersection
      attributeIntersections.push({
        attributes: [attr1, attr2],
        count: intersectionCount,
        percentage: (intersectionRate * 100).toFixed(2),
        lift: liftScore.toFixed(2)
      });
    }
  }
}

// Sort by lift score (how much more likely to co-occur than random)
attributeIntersections.sort((a, b) => parseFloat(b.lift) - parseFloat(a.lift));

// Step 5: Build multi-attribute clusters (3+ attributes)
const multiAttributeClusters = [];
const topPairs = attributeIntersections.slice(0, 5);

for (const pair of topPairs) {
  // Try adding each remaining top attribute
  for (let k = 0; k < Math.min(10, topAttributes.length); k++) {
    const attr3 = topAttributes[k].attribute;
    if (pair.attributes.includes(attr3)) continue;

    // Count 3-way intersection
    let count3 = 0;
    for (const attributes of personAttributeSets) {
      if (pair.attributes.every(a => attributes.includes(a)) &&
          attributes.includes(attr3)) {
        count3++;
      }
    }

    const rate3 = count3 / totalPersons;
    if (rate3 > 0.25) { // At least 25% have all three
      multiAttributeClusters.push({
        attributes: [...pair.attributes, attr3],
        count: count3,
        percentage: (rate3 * 100).toFixed(2)
      });
    }
  }
}

multiAttributeClusters.sort((a, b) => b.count - a.count);
```

**Example Output:**
```json
{
  "topAttributes": [
    {
      "attribute": "gender=Female",
      "count": 850,
      "percentage": "85.00"
    },
    {
      "attribute": "interested_healthy_living=Yes",
      "count": 720,
      "percentage": "72.00"
    },
    {
      "attribute": "fitness_affinity=High",
      "count": 680,
      "percentage": "68.00"
    }
  ],
  "topIntersections": [
    {
      "attributes": [
        "gender=Female",
        "interested_healthy_living=Yes"
      ],
      "count": 650,
      "percentage": "65.00",
      "lift": "1.18"
    },
    {
      "attributes": [
        "gender=Female",
        "fitness_affinity=High"
      ],
      "count": 620,
      "percentage": "62.00",
      "lift": "1.15"
    },
    {
      "attributes": [
        "interested_healthy_living=Yes",
        "household_income_range=$75K-$100K"
      ],
      "count": 480,
      "percentage": "48.00",
      "lift": "1.35"
    }
  ],
  "multiAttributeClusters": [
    {
      "attributes": [
        "gender=Female",
        "interested_healthy_living=Yes",
        "fitness_affinity=High"
      ],
      "count": 580,
      "percentage": "58.00"
    },
    {
      "attributes": [
        "gender=Female",
        "interested_healthy_living=Yes",
        "household_income_range=$75K-$100K"
      ],
      "count": 420,
      "percentage": "42.00"
    }
  ]
}
```

**Key Insights:**
- **Top Attributes**: Individual characteristics sorted by frequency
- **Top Intersections**: Pairs of attributes that co-occur frequently
  - `lift > 1.0` means they appear together more than random chance
  - Higher lift = stronger association between attributes
- **Multi-Attribute Clusters**: 3+ attributes that define cohesive segments
  - These represent your true ICP - the combination of traits that define your best customers
  - Use these for high-precision targeting (AND logic)
- **Strategy**:
  - Use multi-attribute clusters with AND for **precision** (smaller, high-quality audience)
  - Use top individual attributes with OR for **reach** (larger, broader audience)

---

### Step 4: Map Attributes to Cluster IDs

To use attributes in `find_persons`, you need to look up their cluster IDs using `list_clusters`.

**Tool:** `list_clusters`

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "list_clusters",
    "arguments": {
      "cluster_names": [
        "gender",
        "interested_healthy_living",
        "fitness_affinity",
        "household_income_range",
        "interested_fitness"
      ]
    }
  },
  "id": 4
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "clusters": [
      {
        "cluster_id": "1000000145",
        "cluster_name": "gender",
        "cluster_value": "Female",
        "member_count": 85000000,
        "domain": "demographic"
      },
      {
        "cluster_id": "1000001556",
        "cluster_name": "interested_healthy_living",
        "cluster_value": "Yes",
        "member_count": 45000000,
        "domain": "interest"
      },
      {
        "cluster_id": "1000000045",
        "cluster_name": "fitness_affinity",
        "cluster_value": "High",
        "member_count": 12000000,
        "domain": "affinity"
      },
      {
        "cluster_id": "1000001234",
        "cluster_name": "household_income_range",
        "cluster_value": "$75K-$100K",
        "member_count": 28000000,
        "domain": "household"
      },
      {
        "cluster_id": "1000001523",
        "cluster_name": "interested_fitness",
        "cluster_value": "Yes",
        "member_count": 35000000,
        "domain": "interest"
      }
    ],
    "total": 5,
    "returned": 5,
    "tool_trace_id": "xyz789...",
    "workflow_id": "550e8400-e29b-41d4-a716-446655440000"
  },
  "id": 4
}
```

**Key Points:**
- Each cluster represents a specific attribute value (e.g., gender=Female)
- `cluster_id` is what you'll use in the boolean expression for `find_persons`
- `member_count` shows the population size for each cluster (useful for estimating audience size)
- You must match both `cluster_name` AND `cluster_value` to your attributes
- Use these cluster IDs in the next step to build your lookalike audience

---

### Step 5: Find Lookalike Audience

Use the cluster IDs from Step 4 to find similar people. You can use two strategies:
- **Precision Targeting** (AND): Find people matching all key attributes
- **Reach Targeting** (OR): Find people matching any key attribute

**Tool:** `find_persons`

**Precision Approach (Recommended):**
Use the multi-attribute cluster to find high-quality matches. This targets the core ICP.

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "find_persons",
    "arguments": {
      "expression": "1000000145 AND 1000001556 AND 1000000045",
      "identifier_type": "email"
    }
  },
  "id": 5
}
```

**Reach Approach:**
Use individual top clusters to cast a wider net for discovery.

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "find_persons",
    "arguments": {
      "expression": "1000000145 OR 1000001556 OR 1000000045",
      "identifier_type": "email"
    }
  },
  "id": 5
}
```

**Hybrid Approach:**
Combine required attributes (AND) with optional attributes (OR) for balanced targeting.

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "find_persons",
    "arguments": {
      "expression": "(1000000145 AND 1000001556) OR (1000000145 AND 1000000045)",
      "identifier_type": "email"
    }
  },
  "id": 5
}
```

**Response (Precision Approach):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "total": 450000,
    "sample": [
      {
        "person_id": 99999,
        "identifiers": {
          "email": {
            "email1": "prospect1@example.com",
            "email2": "prospect1-alt@example.com"
          }
        }
      },
      {
        "person_id": 88888,
        "identifiers": {
          "email": {
            "email1": "prospect2@example.com"
          }
        }
      }
    ],
    "tool_trace_id": "ghi789...",
    "workflow_id": "550e8400-e29b-41d4-a716-446655440000"
  },
  "id": 5
}
```

**Response (Reach Approach):**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "total": 8500000,
    "sample": [...]
  }
}
```

**Key Points:**
- `expression` uses boolean logic with cluster IDs (AND, OR, NOT operators)
- **Precision (AND)**: Smaller audience, higher match quality
  - Example: 450K people matching ALL 3 attributes
  - Best for: High-value campaigns, limited budgets, quality over quantity
- **Reach (OR)**: Larger audience, broader targeting
  - Example: 8.5M people matching ANY of 3 attributes
  - Best for: Brand awareness, discovery, testing new segments
- **Hybrid**: Balanced approach using combinations
  - Example: Multiple AND groups connected by OR
  - Best for: Testing multiple ICP variants simultaneously
- `total` shows the full audience size (use this to estimate campaign reach)
- `sample` provides up to 10 preview records
- Use `identifier_type` to specify which contact info to return (email, phone, or address)
- For full export, add `"format": "csv"` or `"format": "json"`

**Strategy Recommendations:**

| Approach | Audience Size | Match Quality | Use Case |
|----------|--------------|---------------|----------|
| AND (3+ clusters) | 100K - 1M | Very High | Core ICP, high-value offers |
| AND (2 clusters) | 500K - 5M | High | Standard campaigns |
| Hybrid | 1M - 10M | Medium-High | A/B testing segments |
| OR | 5M+ | Variable | Discovery, cold outreach |

**Expression Examples:**
```javascript
// Precision: Core ICP with all defining traits
"1000000145 AND 1000001556 AND 1000000045"  // 450K people

// With exclusions: Remove known non-converters
"(1000000145 AND 1000001556) AND NOT 1000002000"  // 380K people

// Multiple precise segments: Test different ICP hypotheses
"(1000000145 AND 1000001556 AND 1000000045) OR (1000000145 AND 1000001234 AND 1000001523)"  // 720K people

// Reach: Any matching characteristic
"1000000145 OR 1000001556 OR 1000000045"  // 8.5M people
```

---

### Step 6: Enrich Lookalike Profiles (Optional)

Retrieve full profiles for the lookalike audience to validate the match quality or for further analysis.

**Tool:** `get_person`

**Request:**
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "get_person",
    "arguments": {
      "person_ids": [99999, 88888, 77777],
      "domains": [
        "demographic", "interest", "affinity", "household"
      ],
      "format": "none"
    }
  },
  "id": 6
}
```

**Response:** *(Same structure as Step 2)*

**Key Points:**
- You can request only specific domains instead of all domains
- Compare lookalike profiles to original customer profiles to validate similarity
- Use enriched data for personalized outreach campaigns

---

## Performance Considerations

### Batching

- **resolve_identities**: No per-request limit, but use `format: "json"` for large datasets
- **get_person**: Max 1000 person_ids per request; batch larger datasets
- **find_persons**: Returns up to 10 sample records inline; use export for full results

### Export Formats

- `"format": "none"` - Inline response (best for < 100 records)
- `"format": "json"` - S3 export as JSON (best for analysis)
- `"format": "csv"` - S3 export as CSV (best for imports to other tools)
- `"format": "jsonl"` - S3 export as JSON Lines (best for streaming/large datasets)

### Workflow ID Tracking

Pass the `workflow_id` from the first response through all subsequent requests to track the entire workflow:

```json
{
  "name": "get_person",
  "arguments": {
    "person_ids": [...],
    "domains": [...],
    "workflow_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

This enables:
- End-to-end request tracing
- Performance analysis across tools
- Feedback submission for data quality issues

---

## Common Variations

### Geographic Targeting

Add location filters to `find_persons`:

```json
{
  "expression": "1000000145 OR 1000001556",
  "location": {
    "latitude": 37.7749,
    "longitude": -122.4194,
    "radius": 50,
    "unit": "miles"
  }
}
```

### Exclude Existing Customers

Build an exclusion list:

```json
{
  "expression": "(1000000145 OR 1000001556) AND NOT (1000002000 OR 1000002001)"
}
```

Where `1000002000` and `1000002001` are clusters that identify your existing customers.

### Multi-Segment ICP

Analyze multiple customer segments separately:

```javascript
// Segment 1: High-value customers
const segment1Clusters = analyzeCluster(highValueCustomers);
const segment1Expression = segment1Clusters.join(" OR ");

// Segment 2: Frequent purchasers
const segment2Clusters = analyzeCluster(frequentPurchasers);
const segment2Expression = segment2Clusters.join(" OR ");

// Combined audience
const combinedExpression = \`(\${segment1Expression}) OR (\${segment2Expression})\`;
```

---

## Error Handling

### Identity Resolution Failures

If `stats.rate < 0.5`, consider:
- Identifier quality (are emails valid?)
- Hash type mismatch (plaintext vs. hashed)
- Formatting (phones should be digits only, no country code)

### Empty Lookalike Audience

If `total: 0`, consider:
- Expression too restrictive (try OR instead of AND)
- Rare cluster combinations
- Use `get_cluster` tool to understand cluster size before building expressions

### Export URL Expiration

Export URLs expire after 1 hour. If expired:
- Re-run the original request
- Download and cache results immediately
- Use workflow_id to track retries

---

## Next Steps

After building your lookalike audience:

1. **Activate in marketing platforms** - Export as CSV and upload to ad platforms
2. **Validate campaign performance** - Track conversion rates and refine clusters
3. **Iterate on cluster selection** - Test different cluster combinations
4. **Feedback loop** - Use `submit_feedback` tool to report data quality issues

**Related Workflows:**
- [ICP Analysis N8N Workflow](/docs/workflows/icp-analysis) - No-code automation with AI-driven segmentation
- [Identity Enrichment](/docs/guides/identity-enrichment) - Enrich customer identifiers with demographic and behavioral data
- [Criteria-Based Audiences](/docs/guides/criteria-based-audiences) - Build targeted audiences using cluster criteria and location filters