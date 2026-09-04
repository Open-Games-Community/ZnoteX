-- Allows imported MyAAC/Gesior gallery URLs to fit without truncation.

ALTER TABLE `znote_images`
  MODIFY `image` varchar(255) NOT NULL;
