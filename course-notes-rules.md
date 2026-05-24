# Course Notes Rules

Use this file as the standard for taking notes from any programming course.

The goal is not to write a transcript. The goal is to create useful notes that help you remember the main ideas from each episode and apply them later.

---

## The Structure

For each episode, alternate between lessons and their matching examples:

- Lesson (one bullet)
- Example directly under that lesson
- Lesson (one bullet)
- Example directly under that lesson

**Do not** separate all lessons first and all examples later.

**Template:**

````md
## Episode 01 — Episode Title

- **Short, single lesson.**
  ```php
  // Small snippet that demonstrates this lesson
  ```

- **Another short, single lesson.**
  ```php
  // Small snippet that demonstrates this lesson
  ```
````

---

## Rules for Lessons

### 1. Keep lessons concise and meaningful

Use one bullet per idea.

- Default: one sentence.
- Optional: add one short explanation sentence (`why` or `when`).
- If it needs more than two short lines, split into multiple bullets.

Good:

```md
- Controllers should stay small because mixing validation, authorization, database logic, and response formatting makes methods harder to read and maintain.
```

Avoid:

```md
- A single bullet that combines multiple rules or multiple decisions.
```

---

### 2. Use simple words

Write notes in your own words.

Good:

```md
- A migration is a version-controlled change to the database.
```

Avoid copying long explanations from the course.

---

### 3. Focus on what matters

Do not write every detail from the episode.

Write only:

- The main idea
- The rule or pattern being taught
- The part you are likely to forget
- The thing you can use in a real project

---

### 4. Include context: why or when

Help yourself remember *where* to apply the lesson.

Add a brief "when/why" clue so you can quickly decide if this applies later.

Good:

```md
- Use small controllers to keep each method testable and easy to change.
- Use migrations to track database schema changes in version control.
```

Avoid:

```md
- Controllers are small.
- Migrations exist.
```

---

### 5. Write lessons as retrievable answers

When you write a lesson, imagine someone asking you the inverse question.

This builds active recall — you can later cover the example and test yourself.

Good (retrievable):

```md
- Use small controllers to keep each method testable and easy to change.
(Answer to: "Why should controllers be small?")
```

Works but less retrievable:

```md
- Controllers should be small.
(Question is vague)
```

---

## Rules for Examples

### 1. Pair each lesson with exactly one small example

The example must demonstrate the lesson and appear directly underneath it.

Good format:

````md
- Route model binding resolves models from route parameters.
  ```php
  Route::get('/posts/{post}', function (Post $post) {
      return view('posts.show', ['post' => $post]);
  });
  ```
````

### 2. Keep examples small

Examples only show one idea, not full code or entire workflows.

Good (small):

```php
$request->validate([
    'title' => ['required', 'max:255'],
]);
```

Avoid: Full controllers, full services, or multi-step workflows.

---

## Formatting Rules

These ensure your notes support recall and recognition:

### 1. Use pair-style bullets (lesson + code block)

This structure is enforced by "The Structure" section above:

````md
- **Lesson text here.**
  ```php
  // Example code
  ```
````

### 2. Write short, not long

Avoid paragraphs and multi-line explanation. Use bullets. This supports scannability and recall.

### 3. Use code block language tags

Always specify the language: `php`, `bash`, `js`, `env`, `sql`, etc. Syntax highlighting helps recognition.

### 4. Make notes readable in under one minute

A solid episode should be scannable and reviewable in 60 seconds. This supports spaced repetition and active recall.

---

## Golden Rule

**Write the lesson, show the matching example immediately, and move on.**

Stop here. You have all the essentials.

