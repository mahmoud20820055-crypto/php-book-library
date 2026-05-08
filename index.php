<?php
session_start();

// ------------------------------------------------------------
// 1. البيانات الأساسية
// ------------------------------------------------------------
$genres = ["Fiction", "Non-Fiction", "Science", "History", "Biography", "Technology"];

$books = [
    ["id" => 1, "title" => "The Great Gatsby", "author" => "F. Scott Fitzgerald", "genre" => "Fiction", "year" => 1925, "pages" => 180, "image_url" => ""],
    ["id" => 2, "title" => "Sapiens", "author" => "Yuval Noah Harari", "genre" => "History", "year" => 2011, "pages" => 443, "image_url" => ""],
    ["id" => 3, "title" => "Brief Answers to the Big Questions", "author" => "Stephen Hawking", "genre" => "Science", "year" => 2018, "pages" => 230, "image_url" => ""]
];

// دالة مساعدة لإيجاد أكبر ID
function getNextId($booksArray) {
    $max = 0;
    foreach ($booksArray as $b) {
        if ($b['id'] > $max) $max = $b['id'];
    }
    return $max + 1;
}

// ------------------------------------------------------------
// 2. معالجة POST (إضافة، تعديل، حذف)
// ------------------------------------------------------------
$errors = [];
$submittedData = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --------------------------------------------------------
    // حالة الحذف (قبل التحقق لأنها لا تحتاج validation)
    // --------------------------------------------------------
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        foreach ($books as $key => $book) {
            if ($book['id'] == $deleteId) {
                unset($books[$key]);
                break;
            }
        }
        $books = array_values($books); // إعادة ترقيم المفاتيح
        $_SESSION['success'] = "Book deleted successfully!";
        header("Location: index.php");
        exit;
    }

    // --------------------------------------------------------
    // جمع البيانات وتنظيفها
    // --------------------------------------------------------
    $submittedData = [
        'title' => trim(htmlspecialchars($_POST['title'] ?? '')),
        'author' => trim(htmlspecialchars($_POST['author'] ?? '')),
        'genre' => $_POST['genre'] ?? '',
        'year' => trim($_POST['year'] ?? ''),
        'pages' => trim($_POST['pages'] ?? ''),
        'image_url' => trim(htmlspecialchars($_POST['image_url'] ?? ''))
    ];

    // التحقق من صحة البيانات
    // title
    if (empty($submittedData['title'])) {
        $errors['title'] = "Title is required.";
    } elseif (strlen($submittedData['title']) < 3 || strlen($submittedData['title']) > 120) {
        $errors['title'] = "Title must be between 3 and 120 characters.";
    }

    // author (يجب أن يحتوي على مسافة على الأقل)
    if (empty($submittedData['author'])) {
        $errors['author'] = "Author is required.";
    } elseif (!str_contains($submittedData['author'], ' ')) {
        $errors['author'] = "Author must have at least first and last name.";
    }

    // genre
    if (empty($submittedData['genre'])) {
        $errors['genre'] = "Genre is required.";
    } elseif (!in_array($submittedData['genre'], $genres)) {
        $errors['genre'] = "Invalid genre selected.";
    }

    // year
    $currentYear = (int)date('Y');
    if (empty($submittedData['year'])) {
        $errors['year'] = "Year is required.";
    } elseif (!preg_match('/^\d{4}$/', $submittedData['year'])) {
        $errors['year'] = "Year must be 4 digits.";
    } else {
        $yearVal = (int)$submittedData['year'];
        if ($yearVal < 1000 || $yearVal > $currentYear) {
            $errors['year'] = "Year must be between 1000 and $currentYear.";
        }
    }

    // pages
    if (empty($submittedData['pages'])) {
        $errors['pages'] = "Pages is required.";
    } elseif (!ctype_digit($submittedData['pages']) || (int)$submittedData['pages'] <= 0) {
        $errors['pages'] = "Pages must be a positive integer.";
    }

    // image_url (اختياري لكن تحقق من الامتداد إذا أُدخل)
    $imageUrl = $submittedData['image_url'];
    if (!empty($imageUrl)) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $errors['image_url'] = "Image URL must end with .jpg, .jpeg, .png, or .gif";
        }
    }

    // --------------------------------------------------------
    // إذا لم توجد أخطاء: إضافة أو تعديل
    // --------------------------------------------------------
    if (empty($errors)) {
        $isEdit = isset($_POST['edit_id']) && !empty($_POST['edit_id']);
        $editId = $isEdit ? (int)$_POST['edit_id'] : null;

        $newBook = [
            'title' => $submittedData['title'],
            'author' => $submittedData['author'],
            'genre' => $submittedData['genre'],
            'year' => (int)$submittedData['year'],
            'pages' => (int)$submittedData['pages'],
            'image_url' => $imageUrl
        ];

        if ($isEdit) {
            // تعديل كتاب موجود
            foreach ($books as $key => $book) {
                if ($book['id'] == $editId) {
                    $newBook['id'] = $editId;
                    $books[$key] = $newBook;
                    break;
                }
            }
            $_SESSION['success'] = "Book updated successfully!";
        } else {
            // إضافة جديدة
            $newBook['id'] = getNextId($books);
            $books[] = $newBook;
            $_SESSION['success'] = "Book added successfully!";
        }

        // تفريغ البيانات المعاد تعبئتها
        $submittedData = [];
        header("Location: index.php");
        exit;
    }
}

// ------------------------------------------------------------
// 3. معالجة GET (edit, delete modal, search, sort)
// ------------------------------------------------------------
$editBook = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    foreach ($books as $book) {
        if ($book['id'] == $editId) {
            $editBook = $book;
            $submittedData = $editBook; // لتعبيئة الفورم
            break;
        }
    }
}

// البحث
$searchTerm = $_GET['search'] ?? '';
$filteredBooks = $books;
if (!empty($searchTerm)) {
    $filteredBooks = array_filter($books, function($book) use ($searchTerm) {
        return stripos($book['title'], $searchTerm) !== false || stripos($book['author'], $searchTerm) !== false;
    });
}

// الترتيب
$sortColumn = $_GET['sort'] ?? 'id';
$sortOrder = $_GET['order'] ?? 'asc';
if (in_array($sortColumn, ['id', 'title', 'author', 'genre', 'year', 'pages'])) {
    usort($filteredBooks, function($a, $b) use ($sortColumn, $sortOrder) {
        if ($sortColumn == 'year' || $sortColumn == 'pages' || $sortColumn == 'id') {
            $valA = (int)$a[$sortColumn];
            $valB = (int)$b[$sortColumn];
        } else {
            $valA = strtolower($a[$sortColumn]);
            $valB = strtolower($b[$sortColumn]);
        }
        if ($sortOrder == 'asc') {
            return $valA <=> $valB;
        } else {
            return $valB <=> $valA;
        }
    });
}

// عرض رسالة النجاح إن وجدت
$successMessage = '';
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Library Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- النموذج (الجزء الأيمن) -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><?= $editBook ? 'Edit Book' : 'Add New Book' ?></div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">Please fix the errors below.</div>
                    <?php endif; ?>

                    <form method="post">
                        <?php if ($editBook): ?>
                            <input type="hidden" name="edit_id" value="<?= $editBook['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($submittedData['title'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['title'] ?? '' ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Author *</label>
                            <input type="text" name="author" class="form-control <?= isset($errors['author']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($submittedData['author'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['author'] ?? '' ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Genre *</label>
                            <select name="genre" class="form-select <?= isset($errors['genre']) ? 'is-invalid' : '' ?>">
                                <option value="">-- Select --</option>
                                <?php foreach ($genres as $g): ?>
                                    <option value="<?= $g ?>" <?= (($submittedData['genre'] ?? '') == $g) ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback"><?= $errors['genre'] ?? '' ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Year *</label>
                            <input type="text" name="year" class="form-control <?= isset($errors['year']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($submittedData['year'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['year'] ?? '' ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pages *</label>
                            <input type="text" name="pages" class="form-control <?= isset($errors['pages']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($submittedData['pages'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['pages'] ?? '' ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cover Image URL (optional)</label>
                            <input type="text" name="image_url" class="form-control <?= isset($errors['image_url']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($submittedData['image_url'] ?? '') ?>">
                            <div class="invalid-feedback"><?= $errors['image_url'] ?? '' ?></div>
                        </div>

                        <button type="submit" class="btn btn-primary"><?= $editBook ? 'Update Book' : 'Add Book' ?></button>
                        <?php if ($editBook): ?>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- الجدول (الجزء الأيسر) -->
        <div class="col-md-8">
            <!-- شريط البحث -->
            <form method="get" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by title or author..." value="<?= htmlspecialchars($searchTerm) ?>">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                    <?php if ($searchTerm): ?>
                        <a href="index.php" class="btn btn-outline-danger">Clear</a>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="sort" value="<?= $sortColumn ?>">
                <input type="hidden" name="order" value="<?= $sortOrder ?>">
            </form>

            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                <tr>
                    <th><a href="?sort=id&order=<?= ($sortColumn=='id' && $sortOrder=='asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($searchTerm) ?>" class="text-white text-decoration-none">#</a></th>
                    <th><a href="?sort=title&order=<?= ($sortColumn=='title' && $sortOrder=='asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($searchTerm) ?>" class="text-white text-decoration-none">Title</a></th>
                    <th><a href="?sort=author&order=<?= ($sortColumn=='author' && $sortOrder=='asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($searchTerm) ?>" class="text-white text-decoration-none">Author</a></th>
                    <th><a href="?sort=genre&order=<?= ($sortColumn=='genre' && $sortOrder=='asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($searchTerm) ?>" class="text-white text-decoration-none">Genre</a></th>
                    <th><a href="?sort=year&order=<?= ($sortColumn=='year' && $sortOrder=='asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($searchTerm) ?>" class="text-white text-decoration-none">Year</a></th>
                    <th><a href="?sort=pages&order=<?= ($sortColumn=='pages' && $sortOrder=='asc') ? 'desc' : 'asc' ?>&search=<?= urlencode($searchTerm) ?>" class="text-white text-decoration-none">Pages</a></th>
                    <th>Cover</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (count($filteredBooks) == 0): ?>
                    <tr><td colspan="8" class="text-center">No books found.</td></tr>
                <?php else: ?>
                    <?php foreach ($filteredBooks as $book): ?>
                        <tr>
                            <td><?= $book['id'] ?></td>
                            <td><?= htmlspecialchars($book['title']) ?></td>
                            <td><?= htmlspecialchars($book['author']) ?></td>
                            <td><?= htmlspecialchars($book['genre']) ?></td>
                            <td><?= $book['year'] ?></td>
                            <td><?= $book['pages'] ?></td>
                            <td>
                                <?php if (!empty($book['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($book['image_url']) ?>" width="50" height="50" class="img-thumbnail" alt="cover">
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit_id=<?= $book['id'] ?>&search=<?= urlencode($searchTerm) ?>&sort=<?= $sortColumn ?>&order=<?= $sortOrder ?>" class="btn btn-sm btn-warning">Edit</a>
                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $book['id'] ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مودال تأكيد الحذف -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this book?
            </div>
            <div class="modal-footer">
                <form method="post" id="deleteForm">
                    <input type="hidden" name="delete_id" id="delete_id" value="">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // تمرير id الكتاب إلى مودال الحذف
    const deleteModal = document.getElementById('deleteModal');
    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const bookId = button.getAttribute('data-id');
        const deleteInput = document.getElementById('delete_id');
        deleteInput.value = bookId;
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>