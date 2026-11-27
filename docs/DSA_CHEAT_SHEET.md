# AcadSync — DSA cheat sheet (for system checking)

> Ctrl+F the word the prof says ("stack", "undo", "graph", "binary"…) and read the row.
> Every place a structure is used has a comment in the code: search **`[DSA:`** or **`[ALGORITHM:`**.
> All structures and algorithms are hand-written in **`backend/custom_dsa.php`** (no built-in `sort`, `in_array`, `array_search`, no SQL `ORDER BY`/`WHERE`).

## 1. What is AcadSync?

A website for one class section (e.g. BSINFO-2A). The **President** makes the section; classmates join with a class code.
It keeps the class **subjects and schedule, tasks and deadlines, study groups, activities with attendance, announcements and dues**.
Problem it solves: deadlines, groups and payments were scattered in group chats. Now they're in one place.

## 2. Language and tools

| What | Used |
|---|---|
| Backend (all DSA code) | **PHP 8** (XAMPP), JSON endpoints in `api/` |
| Database | **SQLite** — only stores rows. Reads are `SELECT * FROM table`; all filtering, sorting and searching is done by our own code |
| Frontend | HTML + **Tailwind CSS** + plain **JavaScript** (`Frontend/`) |
| Run | `C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php` → open http://localhost:8000 |
| Tests | `C:\xampp\php\php.exe tests/dsa_test.php` → **220 passed, 0 failed** |
| Live demo of every structure | the **How it works** page (`dsa.html`) |

## 3. The 7 data structures

All 7 are in `backend/custom_dsa.php`.

### Array
- **Like you're 5:** a row of numbered boxes. You can grab box #3 directly.
- **Our code:** `FixedArray` → `SubjectArray`, and the 2D `WeekGrid`
- **Used for:**
  - **Subjects** — `SubjectArray` has 16 boxes. `add()` uses the next free box and says no when full; `get(i)` grabs box *i*.
    Feeds Home *My subjects*, the Week page and the Tree's branches.
  - **Class-time clash check** — `WeekGrid` is a table: 6 rows (Mon–Sat) × 288 columns (5 minutes each).
    A new class time reads only its own boxes; if one is taken → clash.
- **"Why only these two?"** PHP's `[]` is built in (really a hash map), so it isn't *our* array.
  We count only the arrays we wrote ourselves and use for a feature.

### Linked List
- **Like you're 5:** a train. Each car holds one task and is hooked to the car before and after it.
- **Our code:** `TaskLinkedList` (doubly linked), `TaskNode`
- **Used for:**
  - **Task timeline** — each task is inserted in deadline order; expired tasks are removed
  - **← Previous / Next →** buttons on a task page
  - **Deleting a task** unlinks its car

### HashSet
- **Like you're 5:** a bag that instantly says "already inside!" — no duplicates.
- **Our code:** `HashSet`, `StudentHashSet`
- **Used for:**
  - Unique **e-mails, student IDs, usernames** (sign-up)
  - Unique **class codes, section names, subject codes**
  - **One GCash reference = one payment**; who **paid a due in full**
  - Who is **going / checked in / invited** for an activity; who is **picked** for a part
  - **One office per person**; **one group per task**; unique group names
  - **Cancelled classes** ("No class" days); days with an activity on the calendar

### Stack
- **Like you're 5:** a pile of plates. The last one on is the first one off (LIFO).
- **Our code:** `UndoStack`, `StackNode`
- **Used for:**
  - **Undo** for students (mark done, leave a group, cancel a request)
  - Officers' **Undo reject** in the queue (pop the latest)

### Queue
- **Like you're 5:** a line at the canteen. First in line, first served (FIFO).
- **Our code:** `ApprovalQueue`, `QueueNode`
- **Used for:**
  - **Task requests** waiting for approval (only the front can be decided)
  - **Join requests** to the section
  - Activity **waitlist** (the first person waiting gets the freed seat)
  - **Volunteers** for a competition part
  - **Auto-grouping** (shuffled into a line, then taken out into groups)

### Tree
- **Like you're 5:** a family tree. One top, then branches, then leaves.
- **Our code:** `SubjectTree`, `ChecklistTree`, `TreeNode`
- **Used for:**
  - **Section → Subjects → Tasks**, searched **depth-first (DFS)** by the search bar
  - A task's **checklist** with sub-items; progress counted **post-order** (children first, then the parent)

### Graph
- **Like you're 5:** dots (classmates) joined by lines (they were in a group together).
- **Our code:** `DependencyGraph` (adjacency list)
- **Used for:**
  - **How you're connected** on Classmates (BFS shortest path: "You → Ana → Leo")
  - **Suggested groupmates**
  - Who has **never been grouped** (no lines)
  - Finding a group's whole team (BFS)

## 4. Algorithms

| Algorithm (like you're 5) | Function (in `custom_dsa.php`) | Used for |
|---|---|---|
| **Insertion Sort** — slip each card into the right place in your hand | `TaskLinkedList::insertChronologically()`; `classes_on_day()` (services.php) | tasks into the timeline by deadline; today's classes by start time; the newest-first list on My tasks |
| **Bubble Sort** — swap neighbours until the biggest floats up | `bubbleSortByUrgency()` | the **Urgent** view on Schedule (closest deadline first) |
| **Selection Sort** — find the smallest, put it first, repeat | `selectionSortByLastName()`, `selectionSortByStudentId()` | classmates **A–Z**; members on Dues and the attendance sheet; sort by ID before binary search |
| **Merge Sort** — split the pile in halves, sort each, merge back (stable) | `mergeSortBy()` | **notifications** (newest first), **announcements** (pinned on top), **activities**, **dues** (soonest), **attendance ranking**, parts, search results |
| **Linear Search** — look at each item one by one | `linearSearch()`, `linearContains()`, `linearSearchTasks()`, `linearSearchByName()` | **duplicate task warning**, classmates by name, activities by title, "is this id in the group?" |
| **Binary Search** — open the dictionary in the middle, keep halving | `binarySearchByStudentId()`, `countDueBy()` | find a classmate by **student ID**; **"Due by Friday"** count on the sorted timeline |
| (extra) **BFS** — ripples in water, nearest first | `DependencyGraph::shortestPath()`, `teamMembers()` | classmate connection path, group teams |
| (extra) **DFS** — go deep down one branch, then back | `SubjectTree::search()` | search bar over Section → Subjects → Tasks |

## 5. Page by page — what each box uses

### Home (member)

| Box | Uses |
|---|---|
| Due today / This week | Linked List |
| Pending | Queue |
| Upcoming deadlines | Linked List (Insertion Sort order) |
| My subjects | Array |
| Classes today | Insertion Sort + HashSet |
| Announcements | Merge Sort |
| Your sign-ups | Merge Sort + HashSet + Queue |
| Dues / group reminder | HashSet |

### Home (President)

| Box | Uses |
|---|---|
| Waiting for you: N requests | Queue |
| Urgent / This week | Linked List |
| Quick actions | links only |
| Classes today | Insertion Sort + HashSet |
| Officers | HashSet |
| Subjects | Array |

### Schedule

| Box | Uses |
|---|---|
| Task list | Linked List |
| Urgent toggle | Bubble Sort |
| Due by Friday | Binary Search |
| Week of classes | Insertion Sort + HashSet |

### Week / Add subject

| Box | Uses |
|---|---|
| Subjects list | Array |
| Class time clash check | 2D Array (`WeekGrid`) |
| Subject code not used twice | HashSet |

### Subject page

| Box | Uses |
|---|---|
| Upcoming tasks | Tree |

### Task page

| Box | Uses |
|---|---|
| ← Previous / Next → | Doubly Linked List |
| Checklist progress | Tree (post-order) |
| Mark done → Undo | Stack |

### Add task / Request a task

| Box | Uses |
|---|---|
| "Similar task" warning | Linear Search |
| Student request | Queue (joins the back) |
| President's task | Linked List (placed by deadline) |

### Queue (officers)

| Box | Uses |
|---|---|
| Awaiting permission | Queue (only the front can be decided) |
| Waiting to join | Queue + HashSet |
| Rejected requests / Undo | Stack |

### Groups

| Box | Uses |
|---|---|
| One group per task, unique names | HashSet |
| Suggested groupmates | Graph |
| Auto-group | Queue |
| Leave / cancel → Undo | Stack |

### Classmates

| Box | Uses |
|---|---|
| List A–Z | Selection Sort |
| Search by name | Linear Search |
| Search by student ID | Selection Sort + Binary Search |
| How you're connected | Graph + BFS |

### Activities

| Box | Uses |
|---|---|
| Upcoming / Past lists | Merge Sort |
| Going / checked in / invited | HashSet |
| Waitlist | Queue |
| Competition volunteers | Queue |
| Picked for a part | HashSet |
| This month (calendar dots) | HashSet |
| Attendance sheet A–Z | Selection Sort |

### Dues

| Box | Uses |
|---|---|
| Who paid in full | HashSet |
| GCash ref used once | HashSet |
| Members A–Z | Selection Sort |
| Dues, soonest first | Merge Sort |

### Other pages

| Page | Uses |
|---|---|
| Member records: attendance ranking | Merge Sort |
| Member records: one office per person | HashSet |
| Search bar: tasks and subjects | Tree (DFS) |
| Search bar: classmates and activities | Linear Search |
| Notifications, newest first | Merge Sort |
| Sign up: unique e-mail, ID, username | HashSet |
| Create / join a section | HashSet + Queue |
| How it works (`dsa.html`) | shows every structure live |

## 6. If the prof asks…

| Question | Answer / where to show |
|---|---|
| "Show me your stack." | `custom_dsa.php` → `class UndoStack` (`push`, `pop`, `peek`). Demo: Mark a task done → toast **Undo** → it's back. Officers: reject a request → **Undo** |
| "Show me your queue." | `class ApprovalQueue` (`enqueue`, `dequeue`, `peek`). Demo: Ana requests a task → Juan's Queue page shows it at the back; only the front has Approve/Reject |
| "Show me your linked list." | `class TaskLinkedList` (`insertChronologically`, `deleteById`, `neighbors`). Demo: open a task → **Previous / Next** |
| "Show me your hashset." | `class HashSet` (`hash`, `add`, `contains`, buckets). Demo: sign up with an e-mail that exists → refused; a used GCash ref → refused |
| "Show me your tree." | `class SubjectTree` (`search` = DFS), `class ChecklistTree` (`rollup` = post-order). Demo: search bar; tick checklist items → % updates |
| "Show me your graph." | `class DependencyGraph` (`addEdge`, `shortestPath` = BFS). Demo: Classmates → tap a classmate → "how you're connected" |
| "Show me your array." | `class SubjectArray`, `class WeekGrid` (2D). Demo: add a subject at a time another subject uses → "clashes with …" |
| "Why not built-in sort?" | The rule we follow: we wrote every sort/search ourselves. Proof: `grep -rnE "\b(in_array\|array_search\|array_filter\|usort\|sort)\(" api backend` prints nothing |
| "Is it tested?" | `php tests/dsa_test.php` → 220 checks, every structure and algorithm, 0 failed |
| "Is the database doing the work?" | No. Only `SELECT * FROM table`; our PHP loops and `custom_dsa.php` do the filtering/sorting |

## 7. Wrong-input demos (shows error handling)

- Sign up with an e-mail / student ID already used → "An account with this e-mail already exists" / "That student number is already registered" (**HashSet**)
- Add a subject whose time overlaps another → clash message (**2D Array**)
- Record or claim a GCash payment with a reference already used, or not 13 digits → refused (**HashSet** + `GcashPayment`)
- Pay more than the balance → "That's more than what's left"
- RSVP to a full seminar → put on the **waitlist** (Queue); cancel → first in line gets the seat
- Join a group when already in one for that task → refused (**HashSet**)
- Task end time before start time, or a deadline in the past → refused

## 8. Accounts for the demo (our current database, `data/acadsync.db`)

**BSINFO-2A** · class code `B2826K`

| Log in as | Role | Password |
|---|---|---|
| `juan.delacruz` | President (can do everything, incl. dues) | `password123` |
| `arcelll` | Vice President | own password |
| `jeffersoncalaguan` | Secretary (attendance, member records) | own password |
| `maria.santos`, `paolo.reyes`, `ana.bautista`, `kyla.lim`, `marco.cruz`, `carlo.domingo` | Members | `password123` |

Treasurer is **vacant**: dues are handled by Juan (or pick a Treasurer on Home → Officers).

**BSINFO-2B** · class code `K7Q4PM` — `bea.garcia` (President), `leo.navarro`, `ivy.mendoza`, `maria.santos` (Members), all `password123`.
