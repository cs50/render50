from setuptools import setup

setup(
    author="CS50",
    author_email="sysadmins@cs50.harvard.edu",
    classifiers=[
        "Intended Audience :: Education",
        "Programming Language :: Python :: 3",
        "Topic :: Education",
        "Topic :: Utilities"
    ],
    description="This is render50, with which you can render source code as PDFs.",
    install_requires=["backports.shutil_get_terminal_size", "backports.shutil_which", "braceexpand", "beautifulsoup4", "natsort", "Pygments>=2.7.1", "PyPDF2", "requests", "six>=1.10.0", "termcolor", "WeasyPrint>=51"],
    keywords=["render", "render50"],
    name="render50",
    scripts=["render50"],
    url="https://github.com/cs50/render50",
    version="5.0.0"
)
