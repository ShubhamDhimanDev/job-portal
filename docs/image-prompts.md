# AI Image Prompts — "Problem We Solve" Section

Three images are needed for the sticky scroll section on the homepage
(`resources/js/pages/home.tsx`, `ProblemSolveSection`). Generate each with
an AI image tool (Midjourney, DALL·E, Stable Diffusion, etc.), then save the
files at the exact paths below — the code already points at them, so no
code changes are needed once the files exist.

| # | Category | Save to |
|---|---|---|
| 1 | Aging Positions | `public/images/problems/aging-positions.jpg` |
| 2 | Niche & Leadership | `public/images/problems/niche-leadership.jpg` |
| 3 | Recruitment Tech | `public/images/problems/recruitment-tech.jpg` |

The `public/images/problems/` folder doesn't exist yet — create it when you
save the first file. Until the files are added, that half of the card shows
a soft brand-colored gradient placeholder instead of a broken image, so the
section still looks intentional.

## Size & resolution (same spec for all three)

The card image is shown at different crops depending on screen size — full
width and short/landscape on mobile, narrower and taller on tablet/desktop.
One square master image, generated with the subject centered and generous
breathing room on all sides, safely covers every crop via `object-cover`.

- **Aspect ratio to generate:** 1:1 (square)
- **Minimum resolution:** 1600 × 1600px (comfortably sharp up to ~420px
  display width on a 3x retina screen)
- **Format:** JPG, high quality (~85–95%), no visible compression artifacts
- **Composition:** keep the main subject within the center ~60% of the
  frame — the outer ~20% margin on each side may get cropped off on mobile
  (wide/short crop) or desktop (taller/narrower crop)
- **No embedded text, logos, or watermarks** — captions are handled in code

## Shared visual style

Use this style language in every prompt so the three images feel like one
consistent set, and match the warm, editorial-corporate-photography look
already used elsewhere on the site:

> photorealistic corporate editorial photography, warm golden-hour lighting
> with soft amber and gold tones, shallow depth of field, shot on a 50mm
> lens, natural and candid rather than posed, high-end business/office
> setting, muted neutral background, professional color grading, ultra
> detailed, 8k quality

**Avoid:** illustration/cartoon style, harsh flash lighting, cluttered
backgrounds, visible screens with legible fake UI text, distorted hands or
faces, oversaturated colors, stock-photo watermarks.

---

## 1. Aging Positions

**File:** `public/images/problems/aging-positions.jpg`
**Communicates:** a role that has sat open too long — stalled, waiting,
overdue — despite effort already spent on it.

**Prompt:**

> A single empty modern office chair pushed neatly against a clean desk
> near a large window, soft warm late-afternoon light streaming in, a desk
> calendar visible on the desk with several days visibly crossed off, a
> closed laptop and an empty nameplate holder suggesting an unfilled role,
> shallow depth of field with the chair and calendar in soft focus, muted
> beige and warm gold color palette, quiet and still mood conveying a
> position that has been vacant too long. Photorealistic corporate
> editorial photography, warm golden-hour lighting with soft amber and gold
> tones, shot on a 50mm lens, natural and candid, high-end office setting,
> professional color grading, ultra detailed, 8k quality. No people, no
> text, no logos.

---

## 2. Niche & Leadership

**File:** `public/images/problems/niche-leadership.jpg`
**Communicates:** the search for rare, senior, hard-to-find talent —
elevated, singular, executive.

**Prompt:**

> A confident senior executive in tailored business attire standing near a
> floor-to-ceiling boardroom window, looking out over a city skyline at
> golden hour, warm amber light rim-lighting their silhouette, minimalist
> modern boardroom interior softly blurred behind them, composition leaves
> generous negative space around the subject, conveying rarity and
> seniority — a singular figure rather than a crowd. Photorealistic
> corporate editorial photography, warm golden-hour lighting with soft
> amber and gold tones, shallow depth of field, shot on a 50mm lens,
> natural and candid rather than posed, professional color grading, ultra
> detailed, 8k quality. Face not clearly visible or turned away/silhouetted
> to keep it universal, no text, no logos.

---

## 3. Recruitment Tech

**File:** `public/images/problems/recruitment-tech.jpg`
**Communicates:** friction and overwhelm caused by outdated, fragmented
recruitment software.

**Prompt:**

> Close-up over-the-shoulder shot of a recruiter's hands typing on a
> keyboard, a laptop screen in soft focus in the background showing a
> cluttered, dense spreadsheet-style interface with many overlapping
> windows and rows of data (abstract/illegible, not real text), warm desk
> lamp lighting mixed with cool monitor glow, a faint sense of digital
> overwhelm and inefficiency, shallow depth of field keeping focus on the
> hands. Photorealistic corporate editorial photography, warm golden-hour
> accent lighting with soft amber tones balanced against the screen's cool
> light, shot on a 50mm lens, natural and candid, professional color
> grading, ultra detailed, 8k quality. No legible on-screen text, no logos,
> hands anatomically correct and natural.
