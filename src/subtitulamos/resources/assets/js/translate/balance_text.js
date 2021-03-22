function isNumeric(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}

module.exports = function (originalText, opinionated) {
  if (typeof opinionated == "undefined") {
    // The default criteria is opinionated: if there's no difference between
    // leaving a word on either line, it's left on the lower one.
    opinionated = true;
  }

  let text = originalText.replace(/\s(["'])[\n\r]([\w¿¡ñáéíóú])/g, " $1$2"); // Remove linebreaks between "/' and text.
  text = text.replace(/[\n\r]/g, " "); // Delete line breaks, we'll do those
  text = text.replace(/\s\s+/, " "); // Remove multiple consecutive spaces, not relevant

  if (text.length > 81) {
    // We cannot do anything with this, it's beyond max (40+40+1 space)
    return [originalText];
  }

  let dialogLineCount = (text.match(/(?:^|\s)-/g) || []).length;
  let isDialog = text.match(/^\s*-/g) && dialogLineCount == 2;
  if (isDialog) {
    /*
     * Looks like we have a dialog. We must preserve each line separated unless that is not possible.
     */
    let fDialogPos = text.indexOf("-");
    let fDialogPos2 = text.substr(fDialogPos + 1).indexOf(" -") + fDialogPos;
    if (fDialogPos2 - fDialogPos < 40 && text.length - 2 - fDialogPos2 <= 40) {
      //len - 1 due to the space introduced by the line break
      // If they fit in two separate lines, that's how it goes
      let dialogLines = [];
      dialogLines[0] = text.slice(0, fDialogPos2 + 1).trim();
      dialogLines[1] = text.slice(fDialogPos2 + 1, text.length).trim();
      return dialogLines;
    }
  }

  if (text.length <= 40) {
    // Nothing to divide
    return [originalText];
  }

  // Find all separators in the word
  let wordSeparatorAtLoc = [];
  for (let i = 0; i < text.length; ++i) {
    wordSeparatorAtLoc[i] = text[i].match(/[ .,;?!\-¿¡"']/);
  }

  let behind = 0;
  let ahead = text.length;
  let curWordPos = 0;
  let ignoreSepUntilNextWord = false;
  for (let i = 0; i < text.length; ++i) {
    behind++;
    ahead--;

    let curChar = text[i];
    if (curChar == "-") {
      ignoreSepUntilNextWord = true;
      continue;
    }

    // If we find a word separator...
    if (wordSeparatorAtLoc[i]) {
      if (ignoreSepUntilNextWord) continue;

      let nextChar = i + 1 < text.length ? text[i + 1] : null;
      let prevChar = i > 0 ? text[i - 1] : null;

      /*
       * If next char is also a separator (unless its a space), we do not split yet.
       */
      if (
        nextChar &&
        (nextChar.match(/[.,;?!\-]/) || (curChar != " " && nextChar.match(/[¿¡"']/)))
      ) {
        continue;
      }

      /**
       * If next char is alphanumeric (/ accented vocal) or yet another separator,
       * and curChar is an opening separator, continue
       */
      if (nextChar && nextChar.match(/[\w¿¡"'àèìòùáéíóúâêîôû]/i) && curChar.match(/[¿¡"']/)) {
        continue;
      }

      /*
       * Numbers, format: 123.578.213
       * If we're at a dot or space, look forward and backwards. If there's a number, and the
       * next character also is a number, keep consuming characters.
       */
      if (
        (curChar == "." || curChar == " ") &&
        nextChar &&
        prevChar &&
        isNumeric(nextChar) &&
        isNumeric(prevChar)
      ) {
        continue;
      }

      // We want to split if there's more characters behind than ahead OR if there's no more separators
      // and not splitting would cause us to break over 40
      const moreSeparatorsLeft = wordSeparatorAtLoc.some((v, idx) => v && idx > i);
      if (behind >= ahead || (!moreSeparatorsLeft && behind + ahead > 40)) {
        if (curChar == " ") {
          // Move one back, as the space isn't part of the word!
          --i;
          --behind;
          ++ahead;
        }

        // <dir>IfSplit counts the number of characters that we'd actually have if we ignore spaces
        let aheadIfSplit = ahead;
        if (aheadIfSplit > 0) {
          let j = i + 1;
          while (j < text.length) {
            if (text[j] != " ") {
              j--;
              continue;
            }

            aheadIfSplit--;
            j++;
            break;
          }
        }

        let behindIfSplit = behind;
        if (behindIfSplit > 0) {
          let j = i - 1;
          while (j >= 0) {
            if (text[j] != " ") {
              j--;
              continue;
            }

            behindIfSplit--;
            break;
          }
        }

        // Split
        let curWordSize = i - curWordPos;
        let aheadWithWord = aheadIfSplit + curWordSize;
        let behindWithoutWord = behindIfSplit - curWordSize;
        let splitAt = i;

        let diff =
          Math.abs(aheadWithWord - behindWithoutWord) - Math.abs(aheadIfSplit - behindIfSplit);
        if (diff < 0 || (opinionated && diff == 0)) {
          // Should've split on the last separator
          splitAt = curWordPos;
        }

        let lines = [];
        lines[0] = text.slice(0, splitAt + 1).trim();
        lines[1] = text.slice(splitAt + 1, text.length).trim();
        return lines;
      }

      curWordPos = i;
    } else if (ignoreSepUntilNextWord) {
      // We found a char that's not a separator so remove the flag and carry on
      ignoreSepUntilNextWord = false;
    }
  }

  // No division
  return [text];
};
