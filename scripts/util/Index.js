const express = require("express");
const app = express();
// Serve EduBridgeSA static files
app.use('/edubridge', express.static('edubridge'));

app.get("/", (req, res) => {
  res.send("CodingGenie server is running!");
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on port ${PORT}`));
