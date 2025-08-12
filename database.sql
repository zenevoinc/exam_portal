

-- Table structure for table `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seat_number` varchar(32) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student') NOT NULL DEFAULT 'student',
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `seat_number_unique` (`seat_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `users`
-- Default admin user. Password is: password123
INSERT INTO `users` (`id`, `seat_number`, `name`, `email`, `password`, `role`, `status`, `failed_attempts`, `created_at`) VALUES
(1, NULL, 'Admin User', 'admin@example.com', '$2y$10$iMy.o142n22YdSFPGyU2A.OqCgqj3h2k6GzT6Kz5jE/J.gL9u8a8K', 'admin', 'active', 0, '2025-08-12 06:30:00');

-- Table structure for table `exams`
CREATE TABLE `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `allow_result_view` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exams_start_end` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `questions`
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_id` int(11) NOT NULL,
  `set_code` enum('A','B','C') NOT NULL DEFAULT 'A',
  `question_text` text NOT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` enum('a','b','c','d') NOT NULL,
  `marks` decimal(5,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`id`),
  KEY `idx_questions_exam_set` (`exam_id`, `set_code`),
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `student_exam`
CREATE TABLE `student_exam` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `score` decimal(7,2) DEFAULT NULL,
  `status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  `current_question_id` int(11) DEFAULT NULL,
  `answered_count` int(11) NOT NULL DEFAULT 0,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_exam` (`student_id`,`exam_id`),
  KEY `idx_student_exam_status` (`status`),
  CONSTRAINT `fk_student_exam_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_exam_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table structure for table `answers`
CREATE TABLE `answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_exam_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` enum('a','b','c','d') NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_answer` (`student_exam_id`,`question_id`),
  CONSTRAINT `fk_answers_student_exam` FOREIGN KEY (`student_exam_id`) REFERENCES `student_exam` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;