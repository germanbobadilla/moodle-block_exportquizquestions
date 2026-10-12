---
layout: page
title: Home
---

**Export Quiz Questions** is a Moodle block that exports a quiz's questions — or every
quiz's questions in a course at once — in any installed question format (Moodle XML,
GIFT, Aiken, XHTML, and others), preserving question bank structure so a re-import
recreates it automatically.

## Why this exists

Moodle's question bank makes it hard to pull together "everything this quiz actually
uses" when those questions are scattered across several subcategories, or to carry a
whole course's quiz question banks over to a different course. This block builds that
structure for you, then exports it.

## How it works

Every course gets one `[shortname] | Quizzes` category in the question bank — it holds
no questions of its own — with one subcategory per quiz, named after the quiz,
directly underneath. Exporting rebuilds the relevant subcategory (or every
subcategory, for a bulk export) first, so the file always matches the quiz's current
questions.

Random question slots have no single fixed question, so you choose how they're
handled: just as many questions as the quiz uses, or every question in their source
categories.

- **[Installation](installation.html)** — requirements, installing the plugin, capabilities.
- **[Usage](usage.html)** — adding the block, exporting, how the question bank structure works.
- **[Changelog](changelog.html)** — what changed in each release.

## Source

[github.com/germanbobadilla/moodle-block_exportquizquestions](https://github.com/germanbobadilla/moodle-block_exportquizquestions)
