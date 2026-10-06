# CUSTOMER-WALD-SOURCECONTRACT02 Qualification

Date: 6 October 2026

Status: **NEEDS MANAGEMENT CONFIRMATION**

Starting candidate: `9509faef1b5962b92548c7ad253d2869b6606c67`

## Approved source profile

Management confirmed that the supplied seven-column RedZebra XLSX uses `Call` as the
permanent Call No. reference, `Customer` as CustomerCode, and `Site Name` as the
Customer → Site → Plot hierarchy. The implementation recognizes only the exact ordered,
contiguous XLSX signature. It does not broaden the global dictionary.

`Customer Name`, `Arrival` and `Complete Date` remain private unmapped evidence.
They do not create customer identity, completion, Requested Date, Date Agreed or any
other Portal-owned state.

## Read-only workbook evidence

Private workbook SHA-256:
`b89c89813c17de3b3a2016b92861ea7dfd856c17d0af3da8a4c772c3414f293c`

| Evidence | Result |
|---|---:|
| Physical data rows | 4,463 |
| Distinct Call values | 4,369 |
| CustomerCodes | 68 |
| Approved excluded CustomerCode rows | 7 |
| Included rows after exact-code exclusions | 4,456 |
| Canonical three-part hierarchies | 4,407 |
| Included malformed hierarchies | 49 |
| Repeated-Call groups | 88 |
| Rows participating in repeated groups | 182 |
| Additional repeated occurrences | 94 |

Canonical hierarchy coverage is 98.75% of all rows and 98.90% of included rows. The
49 included malformed rows remain visible blockers. No malformed value is guessed.

## Repeated Call classification

All seven raw fields and canonical parsed hierarchy were compared within each repeated
Call group:

| Category | Groups |
|---|---:|
| Canonically identical rows | 1 |
| Same Call/context with date-field differences only | 87 |
| Different Call Type | 0 |
| Different Plot hierarchy | 0 |
| Different CustomerCode | 0 |
| Other meaningful field differences | 0 |

Of the 87 date-variant groups, 84 change Arrival, 61 change Complete Date, 58 change
both, 26 change Arrival only and three change Complete Date only. This classification
does not establish whether they are separate appointments, retained history or source
corrections. No first/last/latest rule and no deduplication is approved. Workbook hash,
worksheet and physical row remain observation provenance, not durable visit identity.

## Browser dry-run recheck

The genuine workbook was uploaded through the local Office **Analyse only — no changes
applied** journey. Header discovery succeeded under the approved profile and analysis
stopped safely at `Duplicate call number` in about 8.6 seconds. It did not reach source
resolution or Apply. Baseline business/import tables remained empty in the disposable
environment; the final before/after check is recorded in the task completion report.

## User-facing failure guidance

Unapproved layouts now report observed header labels, missing or competing controlled
roles, and the approved label families. The message states that no mapping or data was
applied and tells Office not to rename columns or discard repeated rows merely to bypass
the safety gate.

## Compatibility and scope

Knowledge policy v4 pins source-profile registry v1, making older uncommitted
interpretations stale. Pilot discovery schema v8 records the selected profile. The
business dictionary and database schema do not change. No workbook, private row set,
local database or credential is committed. No push, deployment, production access or
Apply is authorised by this qualification.

## Remaining decision

Management must define what multiple rows with the same Call but different Arrival and/or
Complete Date represent. Until then every repeated Call remains a hard blocker and the
safe result is **NEEDS MANAGEMENT CONFIRMATION**.
