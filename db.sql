-- ============================================================
-- Database Schema derived from ER Diagram
-- Engine: MySQL 8.0+
-- ============================================================

CREATE DATABASE IF NOT EXISTS school_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE school_db;

-- ============================================================
-- 1. department
--    独立实体，需先创建，因为 student / teacher 依赖它
-- ============================================================
CREATE TABLE department (
    dept_id    INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    dept_name  VARCHAR(100) NOT NULL,
    dept_code  VARCHAR(20)  NOT NULL,

    PRIMARY KEY (dept_id),
    UNIQUE KEY uq_dept_name (dept_name),
    UNIQUE KEY uq_dept_code (dept_code)
) ENGINE=InnoDB;


-- ============================================================
-- 2. course
-- ============================================================
CREATE TABLE course (
    course_id   INT            UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150)   NOT NULL,
    credit      DECIMAL(3, 1)  NOT NULL CHECK (credit > 0),
    hours       TINYINT        UNSIGNED NOT NULL CHECK (hours > 0),
    capacity    SMALLINT       UNSIGNED NOT NULL CHECK (capacity > 0),
    description TEXT,

    PRIMARY KEY (course_id),
    UNIQUE KEY uq_course_name (name),
    INDEX idx_course_credit (credit)
) ENGINE=InnoDB;


-- ============================================================
-- 3. user  (ISA 父实体)
-- ============================================================
CREATE TABLE user (
    user_id    INT          UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    password   VARCHAR(255) NOT NULL,               -- 存储 hash 后的密文
    status     ENUM('active','inactive','banned')
                            NOT NULL DEFAULT 'active',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    gender     ENUM('male','female','other'),
    phone      VARCHAR(20),
    img        VARCHAR(255),

    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_email (email),
    UNIQUE KEY uq_user_phone (phone),               -- 电话号码全局唯一
    INDEX idx_user_status (status),
    INDEX idx_user_created_at (created_at)
) ENGINE=InnoDB;


-- ============================================================
-- 4. student  (ISA 子实体)
--    belong 1: student.dept_id -> department.dept_id
-- ============================================================
CREATE TABLE student (
    user_id  INT     UNSIGNED NOT NULL,
    grade    VARCHAR(10),                            -- e.g. 'Freshman','A','3.8'
    dept_id  INT     UNSIGNED NOT NULL,

    PRIMARY KEY (user_id),
    CONSTRAINT fk_student_user
        FOREIGN KEY (user_id) REFERENCES user (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_student_dept
        FOREIGN KEY (dept_id) REFERENCES department (dept_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_student_dept (dept_id)
) ENGINE=InnoDB;


-- ============================================================
-- 5. teacher  (ISA 子实体)
--    belong 2: teacher.dept_id -> department.dept_id
-- ============================================================
CREATE TABLE teacher (
    user_id  INT         UNSIGNED NOT NULL,
    title    VARCHAR(50),                            -- e.g. 'Prof.','Dr.','Assoc. Prof.'
    dept_id  INT         UNSIGNED NOT NULL,

    PRIMARY KEY (user_id),
    CONSTRAINT fk_teacher_user
        FOREIGN KEY (user_id) REFERENCES user (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teacher_dept
        FOREIGN KEY (dept_id) REFERENCES department (dept_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_teacher_dept (dept_id)
) ENGINE=InnoDB;


-- ============================================================
-- 6. admin  (ISA 子实体)
-- ============================================================
CREATE TABLE admin (
    user_id INT         UNSIGNED NOT NULL,
    role    VARCHAR(50) NOT NULL DEFAULT 'admin',

    PRIMARY KEY (user_id),
    CONSTRAINT fk_admin_user
        FOREIGN KEY (user_id) REFERENCES user (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- 7. section  (sec_course 联系已内嵌为 course_id 外键)
-- ============================================================
CREATE TABLE section (
    section_id  INT         UNSIGNED NOT NULL AUTO_INCREMENT,
    semester    VARCHAR(20) NOT NULL,               -- e.g. 'Spring','Fall','Summer'
    year        YEAR        NOT NULL,
    course_id   INT         UNSIGNED NOT NULL,

    PRIMARY KEY (section_id),
    CONSTRAINT fk_section_course
        FOREIGN KEY (course_id) REFERENCES course (course_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uq_section (course_id, semester, year),
    INDEX idx_section_course (course_id),
    INDEX idx_section_year_sem (year, semester)
) ENGINE=InnoDB;


-- ============================================================
-- 8. advisor  (联系集：teacher 指导 student，多对多)
-- ============================================================
CREATE TABLE advisor (
    teacher_id  INT UNSIGNED NOT NULL,
    student_id  INT UNSIGNED NOT NULL,

    PRIMARY KEY (teacher_id, student_id),
    CONSTRAINT fk_advisor_teacher
        FOREIGN KEY (teacher_id) REFERENCES teacher (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_advisor_student
        FOREIGN KEY (student_id) REFERENCES student (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_advisor_student (student_id)
) ENGINE=InnoDB;


-- ============================================================
-- 9. teaching  (联系集：teacher 授课 section，多对多)
-- ============================================================
CREATE TABLE teaching (
    teacher_id  INT UNSIGNED NOT NULL,
    section_id  INT UNSIGNED NOT NULL,

    PRIMARY KEY (teacher_id, section_id),
    CONSTRAINT fk_teaching_teacher
        FOREIGN KEY (teacher_id) REFERENCES teacher (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_teaching_section
        FOREIGN KEY (section_id) REFERENCES section (section_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_teaching_section (section_id)
) ENGINE=InnoDB;


-- ============================================================
-- 10. takes  (联系集：student 选课 section，带描述属性 grade)
--     ER 图中 grade 以虚线连到 takes，表示联系集的描述性属性
-- ============================================================
CREATE TABLE takes (
    student_id  INT         UNSIGNED NOT NULL,
    section_id  INT         UNSIGNED NOT NULL,
    grade       VARCHAR(5),                         -- NULL 表示尚未出分

    PRIMARY KEY (student_id, section_id),
    CONSTRAINT fk_takes_student
        FOREIGN KEY (student_id) REFERENCES student (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_takes_section
        FOREIGN KEY (section_id) REFERENCES section (section_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX idx_takes_section (section_id)
) ENGINE=InnoDB;


-- ============================================================
-- 11. exam  (三元联系集：teacher + student + section)
--     一次考试由某位教师主持，面向某位学生，隶属某个教学班
-- ============================================================
CREATE TABLE exam (
    exam_id     INT      UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id  INT      UNSIGNED NOT NULL,
    student_id  INT      UNSIGNED NOT NULL,
    section_id  INT      UNSIGNED NOT NULL,
    exam_date   DATE,
    score       DECIMAL(5, 2) CHECK (score BETWEEN 0 AND 100),

    PRIMARY KEY (exam_id),
    CONSTRAINT fk_exam_teacher
        FOREIGN KEY (teacher_id) REFERENCES teacher (user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_exam_student
        FOREIGN KEY (student_id) REFERENCES student (user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_exam_section
        FOREIGN KEY (section_id) REFERENCES section (section_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uq_exam (teacher_id, student_id, section_id, exam_date),
    INDEX idx_exam_student  (student_id),
    INDEX idx_exam_section  (section_id),
    INDEX idx_exam_date     (exam_date)
) ENGINE=InnoDB;