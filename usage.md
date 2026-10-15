---
layout: page
title: Usage
---

## Exporting

On the block, choose **a specific quiz** or **all quizzes in this course**, pick a
format, then select **Export**.

- **A specific quiz** exports just that quiz's subcategory.
- **All quizzes in this course** rebuilds every quiz's subcategory and exports them
  all together in one file. A quiz with nothing to export is skipped rather than
  aborting the whole run.

## Question bank structure

Every course gets one `[shortname] | Quizzes` category in the question bank (it holds
no questions of its own) with one subcategory per quiz, named after the quiz, directly
underneath. When you export:

1. The relevant subcategory (or subcategories, for a bulk export) is rebuilt: emptied,
   then refilled with the questions that quiz actually uses, taken from whichever
   subcategories, banks, or contexts the quiz draws from.
2. The export is in the format you chose, with the category structure embedded in the
   file.

Importing a **bulk** export into another course — even one with no existing question
bank structure — recreates the same `[shortname] | Quizzes` category and per-quiz
subcategories automatically, with every question back in the right one.

Importing a **single quiz's** export (Moodle XML only) creates a new subcategory named
after the quiz, in whatever category you have selected on Moodle's import screen, and
puts the questions there — rather than this course's own `[shortname] | Quizzes`
grouping category, and rather than adding the questions directly into whatever category
you had selected. Other formats (GIFT, Aiken, etc.) don't support category markers at
all, so a single quiz's export in those formats is a flat list of questions.

## Random question slots

A random slot has no single fixed question, so you choose how it's handled:

- **Only as many questions as the quiz uses** (default) — one representative question
  per random slot. Slots that share an identical filter (e.g. "5 random questions from
  category X") still each get a distinct question.
- **All questions from their source categories** — every question the slot could ever
  draw from, including subcategories and tags if the filter uses them. This can be
  very large if the source category is a big, shared pool.
