# Wald Import — Fenster Office SOP

**Status:** Feature-branch procedure for `customerapp.redzebra-grouped-native.v1`.
Use this direct grouped procedure only after the release containing it is deployed
and the supported profile is visible in the Imports workflow. This document is
not evidence that the profile is on `main` or live.

## 1. Prepare the RedZebra export

Export the standard RedZebra master report without rearranging, sorting, deleting,
renaming or inserting rows or columns. Keep the original workbook intact. The
native profile supports `.xls` and `.xlsx` workbooks up to the application’s
configured file size and analysis limits. Upload the RedZebra master export as
one workbook; do not manually split parent and call-off rows.

The supported native profile expects one visible worksheet, the known RedZebra
header layout, and the grouped A/B parent and detail structure. A different
layout may be treated as a flat workbook or stopped for review. Paired Analyse is
a separate workflow and does not combine its inputs with this direct import.

## 2. Upload and analyse

1. Sign in with an authorised Fenster Office account and open **Imports**.
2. Upload the unmodified workbook and complete the export date/slot details
   requested by the page.
3. Wait for Analyse to finish. Review the source/site totals, Plot and call-off
   totals, exclusions, hierarchy findings, and unknown Call Types.
4. If Analyse reports a blocker, open the affected source rows and retain their
   workbook row numbers and original values for review. Do not guess a Customer,
   Site, Plot or Call Type.

Analyse stores private import/review evidence. It does not itself create or
change Customers, Sites, Plots, services, visits, dates or requests.

## 3. How Wald reads the grouped rows

A parent row establishes one Plot. In the supported profile, column A is the
descriptive site/customer text and column B carries the full Customer → Site →
Plot hierarchy. Child rows below it inherit that Plot until the next valid parent.
Column A on a child row is an opaque Call Number; column B is its Call Type.
Several child calls can belong to one Plot.

Example:

| Column A | Column B | Meaning |
|---|---|---|
| Winkleigh Phase 2 - Allison Homes SW Limited | Allison - Winkleigh (Phase 2) - Plot 098 | Plot parent |
| 1330 | PC1 | Call-off for Plot 098 |
| 5899 | CM1 | Another call-off for Plot 098 |
| Winkleigh Phase 2 - Allison Homes SW Limited | Allison - Winkleigh (Phase 2) - Plot 099 | New Plot parent |

A parent with no call-off still represents inventory. Wald may stage it as
`BASE_PLOT_ROW`; it must not invent a visit, service, completion, requested date
or agreed date. A blank Call Type means no Visit. A nonblank unrecognised Call
Type needs review.

## 4. CustomerCode and Site confirmation

For this direct grouped profile, Wald uses Z when it contains a valid
CustomerCode. If Z is absent, blank or invalid, Wald uses the part of AA before
the first hyphen. Check that this code
matches the intended existing source-site binding. The CustomerCode is the
binding key; descriptive names help verify the hierarchy but do not establish a
new identity by similarity.

Confirm the exact Customer and Site shown in Wald. If a source description has
changed but the established exact binding is correct, use the existing binding.
If the code, hierarchy or target ownership conflicts, stop and ask the Wald
support/development contact. Do not create a duplicate Site to get past the
warning.

## 5. Exceptions and first action

| Finding | Office action |
|---|---|
| Malformed hierarchy | Check the original B cell and row. If the Customer, Site and Plot are not explicit, leave it for manual review. |
| Unknown Call Type | Keep the source value unchanged and request an approved dictionary decision. Do not map it to a familiar service. |
| Orphan detail row | Check the rows above it in the original workbook. Do not attach it to a Plot unless the parent context is unambiguous. |
| Conflicting CustomerCode inside one parent group | Hold the affected rows/site for review; never carry context across the conflict. |
| Duplicate Call Number | Keep both source rows for review. Do not choose the first or last row. |
| Existing binding/ownership conflict | Do not reassign the established source identity or Plot history. Ask an administrator/developer to resolve the exact binding. |
| Product quantity conflict | Leave the selected site unit blocked. Do not choose a winning quantity. |
| Unknown/unclassified row | Retain it for review with workbook row and original cell values. Do not guess its role. |
| Capacity or unsupported workbook warning | Keep the original workbook; do not split or reformat it as a workaround. Ask development to qualify the source/profile. |

Office can confirm an exact target binding or resolve an offered one-time
clarification when the UI supports it. New Call Type meanings, parser/profile
changes, data ownership conflicts and capacity failures require developer or
management review.

## 6. Preview and Apply

The native grouped RedZebra master profile is Analyse-only. Its source rows can
be retained for review, and a non-mutating preview may be viewed if needed, but
approval and commit are blocked until a separately qualified and authorised
release enables that profile. Do not apply this profile through an alternate
route, direct database edit, or terminal command.

Other already-supported import profiles continue to use the existing
one-site-at-a-time flow: confirm the exact source-site binding, run Analyse,
review that site’s preview and blockers, then explicitly confirm that one
site. Check the final receipt before moving to another site. A preview is not a
commit.

## 7. After a reviewed import

Use the receipt/history to confirm which one-site unit completed. Check the
Customer’s Sites and the selected Site’s Plots. Confirm that inventory-only
Plots have no fabricated visit, service, completion or Portal date. Leave
unresolved source findings visible and retain the import history.

## 8. Later exports

Treat each upload as a new source revision under the approved export-order
process. A partial or filtered export does not mean omitted Plots or calls were
deleted. Exact bound site plus normalized Plot Ref reuses an existing Plot;
Plot Ref is not matched globally across Sites. Source imports must preserve
Portal-owned requests, requested/agreed dates, amendments and history.
Do not delete or deactivate existing Customers, Sites or Plots because a source
row is absent or excluded.

## 9. Common upload errors

- **Unsupported structure / bounded table required:** verify the export is the
  unmodified, supported one-sheet report; otherwise ask development to inspect
  its exact structure.
- **CustomerCode missing:** check Z, then the prefix before the first hyphen in
  AA. If neither holds a valid code, leave it for review.
- **Malformed hierarchy:** provide the exact workbook row and A/B cell values;
  do not rename components to force a match.
- **Unknown Call Type:** preserve its exact code and seek an approved meaning.
- **Row limit exceeded:** do not split the workbook or apply in smaller pieces
  unless development provides a qualified workflow.

## 10. Safety

Stop before Apply if the target Customer/Site is unclear, source ownership
conflicts, the preview contains an unexpected Plot or service, or the result
would change Portal-owned dates/history. Excluding a source site from an import
does not authorise deleting or deactivating its existing CustomerApp records.
Keep private workbooks within approved storage; never attach them to a Git
commit or send them to customers.
