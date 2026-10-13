-- ========================================================
-- MALL FÖR ETT FÖRETAGS DATABAS
-- ========================================================
-- Varje kundföretag får en egen databas. Den här filen skapar
-- tabellerna i den databas du har valt. Exempel:
--
--   ddev mysql -uroot -proot -e "CREATE DATABASE company_acme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   ddev mysql -uroot -proot company_acme < database/olddata.sql
--
-- Eftersom allt i databasen tillhör samma företag behövs inget company_id.

-- ========================================================
-- 1. FÖRETAG & ANVÄNDARE
-- ========================================================

-- Företagets uppgifter och kopplingen till The Provider.
-- Bara EN rad (company_id är alltid 1).
CREATE TABLE COMPANY (
    company_id TINYINT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    provider_username VARCHAR(100) NOT NULL, -- Företagets konto hos The Provider, t.ex. "acme_4"
    session_key VARCHAR(255) NULL,           -- KRYPTERAD nyckel från The Provider (aldrig klartext)
    services INT NOT NULL DEFAULT 0,         -- Bitmask: 1 = kalender, 2 = blogg, 4 = wiki
    expires_at DATETIME NULL,                -- När nyckeln från The Provider går ut
    PRIMARY KEY (company_id),
    CHECK (company_id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Företagets anställda
CREATE TABLE USER (
    user_id INT AUTO_INCREMENT NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,     -- password_hash() i PHP
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active BOOLEAN NOT NULL DEFAULT TRUE, -- Inaktivera i stället för att radera
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- De anställdas egna nycklar (en per inloggning)
CREATE TABLE USER_TOKEN (
    token_id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,     -- hash('sha256', $token), själva nyckeln sparas aldrig
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (token_id),
    FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 2. TAGGAR
-- ========================================================

CREATE TABLE TAG (
    tag_id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL UNIQUE,
    PRIMARY KEY (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 3. BILDSYSTEM
-- ========================================================

CREATE TABLE IMAGE (
    image_id INT AUTO_INCREMENT NOT NULL,
    file_path VARCHAR(500) NOT NULL,         -- Slumpat filnamn på disk, inte användarens filnamn
    mime_type VARCHAR(50) NOT NULL,          -- t.ex. "image/jpeg"
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    uploaded_by_user_id INT NOT NULL,
    PRIMARY KEY (image_id),
    FOREIGN KEY (uploaded_by_user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 4. BLOGGER-TJÄNSTEN
-- ========================================================

CREATE TABLE BLOG (
    blog_id INT AUTO_INCREMENT NOT NULL,
    owner_user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    is_public BOOLEAN NOT NULL DEFAULT FALSE, -- TRUE = alla kan läsa, FALSE = bara anställda
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blog_id),
    FOREIGN KEY (owner_user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE BLOG_POST (
    post_id INT AUTO_INCREMENT NOT NULL,
    blog_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_title VARCHAR(255) NULL,
    published_content LONGTEXT NULL,
    draft_title VARCHAR(255) NULL,
    draft_content LONGTEXT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id),
    FOREIGN KEY (blog_id) REFERENCES BLOG(blog_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE BLOG_POST_CONTRIBUTOR (
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('author', 'editor', 'contributor') NOT NULL DEFAULT 'contributor',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES BLOG_POST(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE BLOG_POST_IMAGE (
    post_id INT NOT NULL,
    image_id INT NOT NULL,
    caption VARCHAR(255) NULL,
    PRIMARY KEY (post_id, image_id),
    FOREIGN KEY (post_id) REFERENCES BLOG_POST(post_id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES IMAGE(image_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE BLOG_POST_TAG (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES BLOG_POST(post_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES TAG(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 5. WIKI-TJÄNSTEN
-- ========================================================

CREATE TABLE WIKI_ARTICLE (
    article_id INT AUTO_INCREMENT NOT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_history_id INT NULL,
    current_history_id INT NULL,
    owner_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (article_id),
    FOREIGN KEY (owner_user_id) REFERENCES USER(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE WIKI_CONTRIBUTOR (
    article_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    role ENUM('admin', 'editor') NOT NULL DEFAULT 'editor',
    invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    PRIMARY KEY (article_id, user_id),
    FOREIGN KEY (article_id) REFERENCES WIKI_ARTICLE(article_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE WIKI_HISTORY (
    history_id INT AUTO_INCREMENT NOT NULL,
    article_id INT NOT NULL,
    version_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_by_user_id INT NOT NULL,
    PRIMARY KEY (history_id),
    UNIQUE (article_id, version_number),     -- Samma version kan inte finnas två gånger
    FOREIGN KEY (article_id) REFERENCES WIKI_ARTICLE(article_id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by_user_id) REFERENCES USER(user_id),
    FULLTEXT (title, content)                -- För sökning
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cirkulära referenser på WIKI_ARTICLE
ALTER TABLE WIKI_ARTICLE
    ADD CONSTRAINT fk_wiki_published_history
    FOREIGN KEY (published_history_id) REFERENCES WIKI_HISTORY(history_id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_wiki_current_history
    FOREIGN KEY (current_history_id) REFERENCES WIKI_HISTORY(history_id) ON DELETE SET NULL;

CREATE TABLE WIKI_HISTORY_IMAGE (
    history_id INT NOT NULL,
    image_id INT NOT NULL,
    caption VARCHAR(255) NULL,
    PRIMARY KEY (history_id, image_id),
    FOREIGN KEY (history_id) REFERENCES WIKI_HISTORY(history_id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES IMAGE(image_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE WIKI_ARTICLE_TAG (
    article_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES WIKI_ARTICLE(article_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES TAG(tag_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- 6. KALENDERTJÄNSTEN
-- ========================================================

CREATE TABLE CALENDAR_EVENT (
    event_id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    location VARCHAR(255) NULL,
    is_company_wide BOOLEAN NOT NULL DEFAULT FALSE, -- TRUE = globalt event, syns för alla anställda
    created_by_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    FOREIGN KEY (created_by_user_id) REFERENCES USER(user_id),
    CHECK (end_time >= start_time),
    INDEX (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE EVENT_PARTICIPANT (
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined') NOT NULL DEFAULT 'pending',
    role ENUM('organizer', 'attendee', 'optional') NOT NULL DEFAULT 'attendee',
    invited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    PRIMARY KEY (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES CALENDAR_EVENT(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES USER(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
