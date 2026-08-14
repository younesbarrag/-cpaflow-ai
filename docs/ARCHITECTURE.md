# Architecture

Technical architecture of CPAFlow AI.

## High-Level Application Architecture

```mermaid
graph TB
    subgraph Client["Client"]
        Browser["Browser"]
        AffiliateNetwork["Affiliate Network Server"]
    end

    subgraph Laravel["Laravel Application"]
        subgraph Web["Web Layer (Blade)"]
            Controllers["Controllers"]
            BladeViews["Blade + Alpine.js"]
        end

        subgraph API["API Layer (Sanctum)"]
            ApiControllers["API Controllers"]
            FormRequests["Form Requests"]
        end

        subgraph Business["Business Layer"]
            Actions["Action Classes"]
            Policies["Policies"]
            Services["Services"]
        end

        subgraph Async["Async Layer"]
            Jobs["Queue Jobs"]
            Queue["Laravel Queue"]
        end

        subgraph Data["Data Layer"]
            Models["Eloquent Models"]
            Migrations["Migrations"]
            DB[(Database)]
        end
    end

    subgraph External["External"]
        AIProvider["AI Provider (Groq)"]
        Prism["Prism SDK"]
    end

    Browser -->|"HTTP (session)"| Web
    Browser -->|"HTTP (Sanctum token)"| API
    AffiliateNetwork -->|"GET /postback/{code}"| Controllers

    Web --> Business
    API --> Business
    Business --> Data
    Jobs --> Services
    Services --> Prism
    Prism --> AIProvider
    Jobs --> Queue
    Queue --> Jobs
```

## Laravel Request Lifecycle in This Project

### Web Flow (Blade)

```mermaid
sequenceDiagram
    participant B as Browser
    participant R as Router
    participant M as Middleware
    participant C as Controller
    participant FR as FormRequest
    participant A as Action
    participant P as Policy
    participant E as Eloquent

    B->>R: GET/POST /campaigns
    R->>M: auth middleware
    M->>C: Route matched
    C->>P: Gate::authorize()
    P-->>C: Allowed/Denied
    C->>FR: Validate input
    FR-->>C: Validated data
    C->>A: Execute business logic
    A->>E: Database operations
    E-->>A: Result
    A-->>C: Return
    C-->>B: Response (view/redirect)
```

### Authenticated API Flow

```mermaid
sequenceDiagram
    participant B as Browser (Blade)
    participant API as /api/v1/*
    participant S as Sanctum
    participant FR as FormRequest
    participant A as Action
    participant E as Eloquent

    B->>API: PATCH /api/v1/campaigns/{id}<br/>Authorization: Bearer {token}
    API->>S: Authenticate token
    S-->>API: User authenticated
    API->>FR: Validate + Authorize
    FR->>FR: rules() + authorize()
    FR-->>API: Validated + Authorized
    API->>A: Execute business logic
    A->>E: Database operations
    E-->>A: Result
    A-->>API: Return
    API-->>B: JSON response
```

### AI Async Flow

```mermaid
sequenceDiagram
    participant B as Blade UI
    participant API as API Controller
    participant A as Action
    participant DB as Database
    participant Q as Queue
    participant J as Job
    participant S as Service (Prism)
    participant AI as AI Provider

    B->>API: POST /api/v1/offers/{id}/analyze
    API->>A: RequestOfferAnalysisAction
    A->>DB: Create AiAnalysis (status: pending)
    A-->>API: { analysis, dispatch: true }
    API->>Q: Dispatch AnalyzeOfferJob
    API-->>B: 202 Accepted

    Q->>J: Pick up job
    J->>DB: Update status → processing
    J->>S: AiOfferAnalyzer.analyze()
    S->>AI: Structured prompt via Prism
    AI-->>S: Structured response
    S-->>J: Parsed result
    J->>DB: Update AiAnalysis (status: completed, store results)
    J-->>Q: Done

    loop Polling
        B->>API: GET /api/v1/offers/{id}/analysis
        API->>DB: Fetch AiAnalysis
        DB-->>API: { status, score, ... }
        API-->>B: JSON response
    end
```

### Conversion Postback Flow

```mermaid
sequenceDiagram
    participant AN as Affiliate Network
    participant R as Route
    participant PC as PostbackConversionController
    participant PS as PostbackSigner
    participant RC as RecordConversionAction
    participant DB as Database

    AN->>R: GET /postback/{code}?external_id=abc&token=xyz
    R->>PC: Invoke controller
    PC->>DB: Lookup TrackingLink by code
    DB-->>PC: TrackingLink + Campaign
    PC->>PS: isValid(code, token)
    PS-->>PC: true/false
    alt Invalid token
        PC-->>AN: 403 Forbidden
    else Valid token
        PC->>RC: execute(campaign, external_id, source)
        RC->>DB: Create Conversion (status: pending)
        alt Duplicate external_id
            RC-->>PC: DuplicateConversionException
            PC-->>AN: {"status":"ok","duplicate":true}
        else New conversion
            RC-->>PC: Conversion created
            PC-->>AN: {"status":"ok","duplicate":false}
        end
    end
```

### Conversion Admin Review Flow

```mermaid
sequenceDiagram
    participant Admin as Admin User
    participant UI as Admin Review UI
    participant API as API Controller
    participant Policy as CampaignPolicy
    participant DB as Database

    Admin->>UI: View pending conversions
    UI->>API: GET /admin/conversions
    API->>DB: Fetch pending conversions
    DB-->>API: List of Pending conversions
    API-->>UI: Render review page

    Admin->>UI: Approve conversion
    UI->>API: POST /api/v1/campaigns/{cid}/conversions/{id}/approve
    API->>Policy: approveConversion(user, campaign)
    Policy-->>API: Admin only
    API->>DB: Update status → approved
    API-->>UI: Success

    Admin->>UI: Reject conversion
    UI->>API: POST /api/v1/campaigns/{cid}/conversions/{id}/reject
    API->>Policy: rejectConversion(user, campaign)
    Policy-->>API: Admin only
    API->>DB: Update status → rejected
    API-->>UI: Success
```

## Layered Architecture

```mermaid
graph LR
    subgraph HTTP["HTTP Layer"]
        R[Routes]
        C[Controllers]
        FR[Form Requests]
        MW[Middleware]
    end

    subgraph Auth["Authorization"]
        P[Policies]
        Gate[Gate]
    end

    subgraph Business["Business Layer"]
        A[Actions]
        S[Services]
        DTO[DTOs]
    end

    subgraph Async["Async Layer"]
        J[Jobs]
        Q[Queue Worker]
    end

    subgraph Data["Data Layer"]
        M[Models]
        E[Eloquent]
        DB[(Database)]
    end

    R --> C
    C --> FR
    C --> A
    FR --> MW
    A --> P
    A --> S
    A --> M
    S --> M
    J --> A
    J --> S
    M --> E
    E --> DB
```

### Responsibility Matrix

| Component | What it does | What it does NOT do |
|-----------|-------------|-------------------|
| **Routes** | Maps URLs to controllers | Contains logic |
| **Controllers** | Coordinates HTTP request/response | Business logic, validation rules |
| **Form Requests** | Validates input, checks authorization | Database operations |
| **Actions** | Implements one business use case | HTTP concerns, validation |
| **Policies** | Answers "can this user do X?" | Performs the action |
| **Services** | Reusable technical logic (AI, signing, hashing) | Knows about HTTP |
| **Jobs** | Defers work to queue | Directly handles HTTP |
| **Models** | Relationships, scopes, casts | Business workflows |
| **DTOs** | Immutable data snapshots | Mutable state |

## Component Map

```
app/
├── Actions/
│   ├── Admin/                  # GetUserAction, ListUsersAction, UpdateUserRoleAction
│   ├── AiAnalysis/             # GetOfferAnalysisAction, RequestOfferAnalysisAction
│   ├── AiGeneration/           # GetGenerationAction, GetOfferGenerationsAction, RequestContentGenerationAction
│   ├── Auth/                   # AuthenticateApiUserAction
│   ├── Campaign/               # CreateCampaignAction, UpdateCampaignAction, ActivateCampaignAction, SuspendCampaignAction
│   ├── Conversion/             # RecordConversionAction
│   ├── Dashboard/              # GetDashboardStatisticsAction
│   ├── Offer/                  # CreateOfferAction, UpdateOfferAction, ArchiveOfferAction, RestoreOfferAction
│   └── TrackingLink/           # GenerateTrackingLinkAction, RecordTrackingClickAction
├── DTOs/                       # OfferAiInputSnapshot, OfferContentGenerationSnapshot, LoginResult, DashboardStatisticsPeriod
├── Enums/                      # UserRole, OfferStatus, CampaignStatus, ConversionStatus, AiProcessStatus
├── Exceptions/                 # DuplicateConversionException, InvalidCampaignTransition
├── Http/
│   ├── Controllers/
│   │   ├── Admin/              # ConversionReviewController
│   │   └── Api/V1/             # OfferController, CampaignController, ConversionController, ...
│   ├── Middleware/              # EnsureUserIsAdmin
│   └── Requests/               # Form Request classes
├── Jobs/                       # AnalyzeOfferJob, GenerateContentJob
├── Models/                     # User, Offer, Campaign, TrackingLink, TrackingClick, Conversion, CampaignExpense, AiAnalysis, AiGeneration
├── Policies/                   # OfferPolicy, CampaignPolicy, UserPolicy
└── Services/                   # PostbackSigner, AiOfferAnalyzer, AiContentGenerator, OfferInputHasher, GenerationInputHasher
                                    └── TrackingLink/ (IpHasher, TrackingCodeGenerator)
```

## Data Flow Summary

```mermaid
graph TD
    O[Offer] -->|belongs to| U[User]
    C[Campaign] -->|belongs to| O
    TL[TrackingLink] -->|belongs to| C
    TC[TrackingClick] -->|belongs to| TL
    CV[Conversion] -->|belongs to| C
    CE[CampaignExpense] -->|belongs to| C
    AA[AiAnalysis] -->|belongs to| O
    AG[AiGeneration] -->|belongs to| O

    style O fill:#f9f,stroke:#333
    style C fill:#bbf,stroke:#333
    style CV fill:#bfb,stroke:#333
    style CE fill:#fbb,stroke:#333
    style AA fill:#ffb,stroke:#333
    style AG fill:#fbf,stroke:#333
```
