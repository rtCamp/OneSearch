/**
 * Determine the modified files count.
 *
 * Usage:
 * node determine-modified-files-count.js <file-path-pattern> <file paths delimited by newlines> [path/to/dir]
 *
 * Parameters:
 * <file-path-pattern> - A string pattern to match the file paths against.
 * <file paths delimited by newlines> - A string of file paths separated by newlines.
 * [path/to/dir] - An optional directory path to restrict the search. If not provided, the script will search in the current directory. Passing "ignored" will count *all* files that *don't* match the pattern.
 *
 * Examples:
 *
 * node determine-modified-files-count.js "foo\/bar*|file*" "foo/baz\nfoo/bar/baz\nfile3" "exclude"
 * Output: 1 (because "exclude" returns the counts of files that *don't* match the pattern).
 *
 * node determine-modified-files-count.js "foo\/bar*|file*" "foo/baz\nfoo/bar/baz\nfile3" "foo/bar"
 * Output: 1 (because the search is restricted to "foo/bar")
 *
 * node determine-modified-files-count.js "foo\/bar*|file*" "foo/baz\nfoo/bar/baz\nfile3"
 * Output: (2 because the full paths only match "foo/bar/baz" and "file3" ).
 *
 */
const args = process.argv.slice(2);

// Guard against bad input.
if (args.length < 2) {
	throw new Error("Not enough arguments provided");
}

const pattern = args[0];
const modifiedFiles = args[1].split("\n");
const dirInclude = args[2];

let count;

if ("ignored" === dirInclude) {
	// If the dirInclude is "all", then we ignore the folder paths.
	count = modifiedFiles.reduce((count, file) => {
		if (pattern.split("|").some((pattern) => file.match(pattern))) {
			return count;
		}

		return count + 1;
	}, 0);
} else if (!!dirInclude) {
	// If we have an include directory, then we need to match the pattern and the directory.
	count = modifiedFiles.filter((file) => {
		return file.match(pattern) && file.startsWith(dirInclude);
	}).length;
} else {
	count = modifiedFiles.filter((file) => {
		return file.match(pattern);
	}).length;
}

console.log(count);
